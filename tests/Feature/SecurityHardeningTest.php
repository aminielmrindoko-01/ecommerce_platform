<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\Security\MfaService;
use App\Services\Security\StepUpAuthService;
use App\Services\Security\TotpService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMarketplace;
use Tests\TestCase;

/**
 * Phase 2 security hardening: privilege escalation, ownership, MFA, step-up, fail-closed.
 */
class SecurityHardeningTest extends TestCase
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
        $role = Role::query()->where('name', $rbac)->firstOrFail();
        $user->roles()->sync([$role->id]);
        app(PermissionResolver::class)->forget($user);

        return $user->fresh();
    }

    public function test_legacy_role_cannot_widen_rbac_permissions(): void
    {
        $user = $this->assign(User::factory()->create(), 'customer', 'admin'); // legacy says admin

        $this->assertFalse($user->hasPermission('admin.access'));
        $this->assertFalse($user->isAdmin());
        $this->assertTrue($user->hasPermission('orders.view'));
        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_customer_cannot_escalate_to_admin_via_http(): void
    {
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');
        $target = $this->assign(User::factory()->create(), 'customer', 'customer');

        $this->actingAs($customer)->put('/admin/users/'.$target->id, [
            'role' => 'admin',
            'rbac_role' => 'super_admin',
        ])->assertForbidden();

        $this->assertSame('customer', $target->fresh()->role);
        $this->assertFalse($target->fresh()->isSuperAdmin());
    }

    public function test_admin_cannot_self_assign_super_admin_without_being_super_admin(): void
    {
        $admin = $this->assign(User::factory()->create(), 'admin', 'admin');
        $target = $this->assign(User::factory()->create(), 'customer', 'customer');

        $this->actingAs($admin)
            ->withSession(['auth.step_up_confirmed_at' => now()->timestamp])
            ->put('/admin/users/'.$target->id, [
                'role' => 'admin',
                'rbac_role' => 'super_admin',
            ])
            ->assertRedirect();

        $this->assertFalse($target->fresh()->isSuperAdmin());
    }

    public function test_review_moderator_cannot_become_finance_manager_via_role_endpoint(): void
    {
        $mod = $this->assign(User::factory()->create(), 'review_moderator', 'admin');
        $target = $this->assign(User::factory()->create(), 'customer', 'customer');

        $this->actingAs($mod)
            ->withSession(['auth.step_up_confirmed_at' => now()->timestamp])
            ->put('/admin/users/'.$target->id, [
                'role' => 'admin',
                'rbac_role' => 'finance_manager',
            ])
            ->assertForbidden();
    }

    public function test_vendor_a_cannot_mutate_vendor_b_product_via_put_delete(): void
    {
        [$a] = $this->createVendorUser(['email' => 'va@example.com']);
        [, $storeB] = $this->createVendorUser(['email' => 'vb@example.com']);
        $this->assign($a, 'vendor', 'vendor');
        $productB = $this->createProductForVendor($storeB, ['name' => 'Secret']);

        $this->actingAs($a)->put(route('vendor.products.update', $productB), [
            'name' => 'Stolen',
            'price' => 1,
            'stock' => 1,
        ])->assertForbidden();

        $this->actingAs($a)->delete(route('vendor.products.destroy', $productB))->assertForbidden();
        $this->assertSame('Secret', $productB->fresh()->name);
    }

    public function test_customer_cannot_access_other_wishlist_or_address_mutations(): void
    {
        $a = $this->assign(User::factory()->create(), 'customer', 'customer');
        $b = $this->assign(User::factory()->create(), 'customer', 'customer');
        $address = Address::create([
            'user_id' => $b->id,
            'label' => 'Home',
            'full_name' => 'B',
            'phone' => '+255700000011',
            'line1' => 'X',
            'city' => 'Dar',
            'region' => 'DSM',
            'is_default' => true,
        ]);

        $this->actingAs($a)->delete(route('account.addresses.destroy', $address))->assertForbidden();
        $this->assertDatabaseHas('addresses', ['id' => $address->id, 'user_id' => $b->id]);
    }

    public function test_mass_assignment_blocks_role_and_mfa_fields_on_profile(): void
    {
        $user = $this->assign(User::factory()->create(), 'customer', 'customer');

        $this->actingAs($user)->put(route('account.profile.update'), [
            'name' => 'Safe',
            'email' => $user->email,
            'role' => 'admin',
            'mfa_enabled' => true,
            'is_active' => false,
            'mfa_secret' => 'ATTACK',
        ])->assertRedirect();

        $fresh = $user->fresh();
        $this->assertSame('customer', $fresh->role);
        $this->assertFalse((bool) $fresh->mfa_enabled);
        $this->assertTrue($fresh->is_active);
        $this->assertNull($fresh->mfa_secret);
    }

    public function test_permission_cache_invalidates_after_role_change(): void
    {
        $super = $this->assign(User::factory()->admin()->create(), 'super_admin', 'admin');
        $target = $this->assign(User::factory()->create(), 'product_manager', 'admin');

        $this->assertTrue($target->hasPermission('products.view'));
        $this->assertFalse($target->hasPermission('payments.manage'));

        app(\App\Services\Authorization\RoleAssignmentService::class)
            ->syncRoles($super, $target, ['finance_manager'], 'admin');

        $target = $target->fresh();
        app(PermissionResolver::class)->forget($target);

        $this->assertTrue($target->hasPermission('payments.manage'));
        $this->assertFalse($target->hasPermission('products.create'));
    }

    public function test_fail_closed_inactive_and_missing_roles(): void
    {
        $user = $this->assign(User::factory()->admin()->create(), 'super_admin', 'admin');
        $user->forceFill(['is_active' => false])->save();
        app(PermissionResolver::class)->forget($user);

        $this->assertFalse($user->fresh()->hasPermission('admin.access'));
        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_mfa_enrollment_and_login_challenge(): void
    {
        $user = $this->assign(User::factory()->admin()->create(['password' => 'password']), 'super_admin', 'admin');
        $mfa = app(MfaService::class);
        $totp = app(TotpService::class);

        $enrollment = $mfa->beginEnrollment($user);
        $code = $totp->at($enrollment['secret'], (int) floor(time() / 30));
        $mfa->confirmEnrollment($user->fresh(), $code);

        $this->assertTrue($user->fresh()->hasMfaEnabled());
        $this->assertDatabaseHas('audit_logs', ['action' => 'MFA_ENABLED']);

        // Login should redirect to MFA challenge (session not fully trusted yet).
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('security.mfa.challenge'));

        $this->assertGuest();

        $challengeCode = $totp->at((string) $user->fresh()->mfa_secret, (int) floor(time() / 30));
        $this->withSession(['mfa.pending_user_id' => $user->id])
            ->post(route('security.mfa.challenge.submit'), ['code' => $challengeCode])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_step_up_required_for_role_change(): void
    {
        $super = $this->assign(User::factory()->admin()->create(), 'super_admin', 'admin');
        $target = $this->assign(User::factory()->create(), 'customer', 'customer');

        $this->actingAs($super)
            ->put('/admin/users/'.$target->id, ['role' => 'vendor'])
            ->assertRedirect(route('security.step-up'));

        $this->assertSame('customer', $target->fresh()->role);
        $this->assertTrue(SecurityEvent::query()->where('event', 'STEP_UP_REQUIRED')->exists()
            || AuditLog::query()->where('action', 'STEP_UP_REQUIRED')->exists());
    }

    public function test_step_up_then_role_change_succeeds_and_audits(): void
    {
        $super = $this->assign(User::factory()->admin()->create(['password' => 'password']), 'super_admin', 'admin');
        $target = $this->assign(User::factory()->create(), 'customer', 'customer');

        $this->actingAs($super)
            ->withSession(['auth.step_up_confirmed_at' => now()->timestamp])
            ->put('/admin/users/'.$target->id, [
                'role' => 'vendor',
                'rbac_role' => 'vendor',
            ])
            ->assertRedirect();

        $this->assertSame('vendor', $target->fresh()->role);
        $this->assertDatabaseHas('audit_logs', ['action' => 'USER_ROLE_CHANGED']);
    }

    public function test_hidden_admin_nav_does_not_bypass_backend(): void
    {
        $mod = $this->assign(User::factory()->create(), 'review_moderator', 'admin');

        // UI would hide Roles — backend must still deny.
        $this->actingAs($mod)->get(route('admin.roles'))->assertForbidden();
        $this->actingAs($mod)->get(route('admin.users'))->assertForbidden();
        $this->actingAs($mod)->delete('/admin/products/1')->assertForbidden();
    }

    public function test_cross_customer_order_put_patch_denied(): void
    {
        $a = $this->assign(User::factory()->create(), 'customer', 'customer');
        $b = $this->assign(User::factory()->create(), 'customer', 'customer');
        [, $store] = $this->createVendorUser();
        $product = $this->createProductForVendor($store);

        $order = Order::create([
            'order_number' => 'SEC-B',
            'user_id' => $b->id,
            'total_price' => 1000,
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cod',
            'shipping_method' => 'pickup',
            'shipping_address' => ['full_name' => 'B'],
        ]);
        OrderItem::recordPurchase($order->id, $product->id, 1, 1000);

        $this->actingAs($a)->get(route('account.orders.show', $order))->assertForbidden();
        $this->actingAs($a)->put('/admin/orders/'.$order->id, ['status' => 'shipped'])->assertForbidden();
        $this->actingAs($a)->patch('/admin/orders/'.$order->id.'/payment', [
            'payment_status' => 'paid',
        ])->assertForbidden();
    }
}
