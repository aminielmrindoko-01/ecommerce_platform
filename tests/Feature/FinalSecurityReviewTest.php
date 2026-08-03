<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Authorization\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Security\MfaService;
use App\Services\Security\StepUpAuthService;
use App\Services\Security\TotpService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMarketplace;
use Tests\TestCase;

/**
 * Final PR #10 security review — runtime HTTP verification of controls.
 */
class FinalSecurityReviewTest extends TestCase
{
    use CreatesMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    protected function assign(User $user, string $rbac, string $legacy): User
    {
        $user->forceFill(['role' => $legacy, 'is_active' => true])->save();
        $role = \App\Models\Role::query()->where('name', $rbac)->firstOrFail();
        $user->roles()->sync([$role->id]);
        app(PermissionResolver::class)->forget($user);

        return $user->fresh();
    }

    public function test_step_up_rejects_client_supplied_external_redirect(): void
    {
        $admin = $this->assign(User::factory()->admin()->create(['password' => 'password']), 'admin', 'admin');

        $response = $this->actingAs($admin)
            ->withSession(['step_up.intended' => 'https://evil.example/phish'])
            ->post(route('security.step-up.confirm'), [
                'password' => 'password',
                'intended' => 'https://evil.example/steal',
            ]);

        $response->assertRedirect(route('account.security'));
        $this->assertTrue(app(StepUpAuthService::class)->isSatisfied());
    }

    public function test_step_up_allows_only_relative_internal_intended_from_session(): void
    {
        $admin = $this->assign(User::factory()->admin()->create(['password' => 'password']), 'admin', 'admin');

        $this->actingAs($admin)
            ->withSession(['step_up.intended' => '/admin/users'])
            ->post(route('security.step-up.confirm'), ['password' => 'password'])
            ->assertRedirect('/admin/users');
    }

    public function test_expired_step_up_cannot_be_reused(): void
    {
        $super = $this->assign(User::factory()->admin()->create(), 'super_admin', 'admin');
        $target = $this->assign(User::factory()->create(), 'customer', 'customer');
        $ttl = app(StepUpAuthService::class)->ttlSeconds();

        $this->actingAs($super)
            ->withSession(['auth.step_up_confirmed_at' => now()->timestamp - $ttl - 10])
            ->put('/admin/users/'.$target->id, [
                'role' => 'vendor',
                'rbac_role' => 'vendor',
            ])
            ->assertRedirect(route('security.step-up'));

        $this->assertSame('customer', $target->fresh()->role);
    }

    public function test_vendor_approve_only_cannot_suspend(): void
    {
        $approver = $this->assign(User::factory()->create(), 'vendor_manager', 'admin');
        // Strip suspend while keeping approve (simulate least-privilege split).
        $role = \App\Models\Role::query()->where('name', 'vendor_manager')->firstOrFail();
        // vendor_manager has both in config — temporarily remove suspend from this user via custom role.
        $custom = \App\Models\Role::query()->create([
            'name' => 'vendor_approver_only',
            'display_name' => 'Vendor Approver Only',
            'description' => 'test',
            'is_system' => false,
        ]);
        $approve = \App\Models\Permission::query()->where('name', 'vendors.approve')->firstOrFail();
        $view = \App\Models\Permission::query()->where('name', 'vendors.view')->firstOrFail();
        $adminAccess = \App\Models\Permission::query()->where('name', 'admin.access')->firstOrFail();
        $dash = \App\Models\Permission::query()->where('name', 'dashboard.view')->firstOrFail();
        $custom->permissions()->sync([$approve->id, $view->id, $adminAccess->id, $dash->id]);
        // Config map does not include this role — expandRolePermissions loads DB permissions.
        $approver->roles()->sync([$custom->id]);
        app(PermissionResolver::class)->forget($approver);
        // Clear config map interference: role name not in config uses DB.

        [, $store] = $this->createVendorUser(['email' => 'verified-vendor@example.com']);
        $store->forceFill(['is_verified' => true])->save();

        $this->assertTrue($approver->fresh()->hasPermission('vendors.approve'));
        $this->assertFalse($approver->fresh()->hasPermission('vendors.suspend'));

        $this->actingAs($approver->fresh())
            ->post(route('admin.vendors.toggle', $store->id))
            ->assertForbidden();

        $this->assertTrue((bool) $store->fresh()->is_verified);
    }

    public function test_mfa_enforce_enrollment_blocks_admin_shell(): void
    {
        config(['authorization.mfa.enforce_enrollment' => true]);

        $admin = $this->assign(User::factory()->admin()->create(), 'admin', 'admin');
        $this->assertTrue($admin->requiresMfaEnrollment());
        $this->assertFalse($admin->hasMfaEnabled());

        $this->actingAs($admin)
            ->get('/admin')
            ->assertRedirect(route('security.mfa.enroll'));
    }

    public function test_mfa_recovery_code_is_single_use(): void
    {
        $user = $this->assign(User::factory()->admin()->create(['password' => 'password']), 'admin', 'admin');
        $mfa = app(MfaService::class);
        $totp = app(TotpService::class);

        $enrollment = $mfa->beginEnrollment($user);
        $code = $totp->at($enrollment['secret'], (int) floor(time() / 30));
        $mfa->confirmEnrollment($user->fresh(), $code);

        $recovery = $enrollment['recovery_codes'][0];
        $this->assertTrue($mfa->verifyLogin($user->fresh(), $recovery));
        $this->assertFalse($mfa->verifyLogin($user->fresh(), $recovery));
    }

    public function test_mfa_login_success_is_audited_after_challenge(): void
    {
        $user = $this->assign(User::factory()->admin()->create(['password' => 'password']), 'super_admin', 'admin');
        $mfa = app(MfaService::class);
        $totp = app(TotpService::class);
        $enrollment = $mfa->beginEnrollment($user);
        $mfa->confirmEnrollment($user->fresh(), $totp->at($enrollment['secret'], (int) floor(time() / 30)));

        $challengeCode = $totp->at((string) $user->fresh()->mfa_secret, (int) floor(time() / 30));
        $this->withSession(['mfa.pending_user_id' => $user->id])
            ->post(route('security.mfa.challenge.submit'), ['code' => $challengeCode])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['action' => 'LOGIN_SUCCESS']);
    }

    public function test_audit_logs_are_immutable_and_have_no_mutate_routes(): void
    {
        $admin = $this->assign(User::factory()->admin()->create(), 'admin', 'admin');
        app(AuditLogger::class)->log(action: 'TEST_IMMUTABLE', actor: $admin);

        $log = AuditLog::query()->where('action', 'TEST_IMMUTABLE')->firstOrFail();

        $this->actingAs($admin)->put('/admin/audit-logs/'.$log->id, ['action' => 'FORGED'])->assertNotFound();
        $this->actingAs($admin)->delete('/admin/audit-logs/'.$log->id)->assertNotFound();

        $this->expectException(\RuntimeException::class);
        $log->update(['action' => 'FORGED']);
    }

    public function test_security_events_are_immutable(): void
    {
        $admin = $this->assign(User::factory()->admin()->create(), 'admin', 'admin');
        app(AuditLogger::class)->security('TEST_SEC_IMMUTABLE', $admin, 'low', ['x' => 1]);
        $event = SecurityEvent::query()->where('event', 'TEST_SEC_IMMUTABLE')->firstOrFail();

        $this->expectException(\RuntimeException::class);
        $event->delete();
    }

    public function test_vendor_cannot_self_verify_via_profile_mass_assignment(): void
    {
        [$user, $store] = $this->createVendorUser(['email' => 'unverified@example.com']);
        $this->assign($user, 'vendor', 'vendor');
        $store->forceFill(['is_verified' => false])->save();

        $this->actingAs($user)->put(route('vendor.profile.update'), [
            'store_name' => $store->store_name,
            'is_verified' => true,
            'rating_avg' => 5,
            'user_id' => 999,
        ])->assertRedirect();

        $fresh = $store->fresh();
        $this->assertFalse((bool) $fresh->is_verified);
        $this->assertSame($user->id, (int) $fresh->user_id);
    }

    public function test_auditor_cannot_reach_finance_payment_mutation(): void
    {
        $auditor = $this->assign(User::factory()->create(), 'auditor', 'admin');
        [, $store] = $this->createVendorUser();
        $product = $this->createProductForVendor($store);
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');

        $order = \App\Models\Order::create([
            'order_number' => 'AUD-1',
            'user_id' => $customer->id,
            'total_price' => 1000,
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cod',
            'shipping_method' => 'pickup',
            'shipping_address' => ['full_name' => 'C'],
        ]);
        \App\Models\OrderItem::recordPurchase($order->id, $product->id, 1, 1000);

        $this->actingAs($auditor)
            ->patch(route('admin.orders.payment', $order), ['payment_status' => 'paid'])
            ->assertForbidden();
    }

    public function test_inventory_manager_cannot_assign_super_admin(): void
    {
        $inv = $this->assign(User::factory()->create(), 'inventory_manager', 'admin');
        $target = $this->assign(User::factory()->create(), 'customer', 'customer');

        $this->actingAs($inv)
            ->withSession(['auth.step_up_confirmed_at' => now()->timestamp])
            ->put('/admin/users/'.$target->id, [
                'role' => 'admin',
                'rbac_role' => 'super_admin',
            ])
            ->assertForbidden();
    }

    public function test_audit_scrub_redacts_mfa_secrets(): void
    {
        $logger = app(AuditLogger::class);
        $user = $this->assign(User::factory()->create(), 'customer', 'customer');
        $logger->log(
            action: 'TEST_SCRUB',
            actor: $user,
            newValues: [
                'mfa_secret' => 'SHOULD_NOT_APPEAR',
                'password' => 'plain',
                'ok' => 'visible',
            ],
        );

        $row = AuditLog::query()->where('action', 'TEST_SCRUB')->firstOrFail();
        $this->assertSame('[redacted]', $row->new_values['mfa_secret']);
        $this->assertSame('[redacted]', $row->new_values['password']);
        $this->assertSame('visible', $row->new_values['ok']);
    }

    public function test_address_user_id_not_mass_assignable(): void
    {
        $a = $this->assign(User::factory()->create(), 'customer', 'customer');
        $b = $this->assign(User::factory()->create(), 'customer', 'customer');

        $this->actingAs($a)->post(route('account.addresses.store'), [
            'label' => 'Hack',
            'full_name' => 'A',
            'phone' => '+255700000099',
            'line1' => 'Line',
            'city' => 'Dar',
            'user_id' => $b->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('addresses', [
            'label' => 'Hack',
            'user_id' => $a->id,
        ]);
        $this->assertDatabaseMissing('addresses', [
            'label' => 'Hack',
            'user_id' => $b->id,
        ]);
    }
}
