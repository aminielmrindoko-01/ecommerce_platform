<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\RoleAssignmentService;
use App\Services\Authorization\SuperAdminBootstrapService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

class SuperAdminBootstrapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_successful_bootstrap_creates_hashed_super_admin(): void
    {
        $plain = 'SecurePass123!';
        $user = app(SuperAdminBootstrapService::class)->create(
            'Bootstrap Admin',
            'bootstrap@example.com',
            $plain,
            $plain,
        );

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'bootstrap@example.com',
        ]);
        $this->assertTrue($user->isSuperAdmin());
        $this->assertContains('super_admin', $user->roleNames());
        $this->assertTrue($user->hasPermission('admin.access'));
        $this->assertTrue($user->hasPermission('permissions.assign'));
        $this->assertTrue(Hash::check($plain, $user->getAttributes()['password']));
        $this->assertNotSame($plain, $user->getAttributes()['password']);

        // Privileges come from RBAC, not merely users.role.
        $this->assertSame('admin', $user->role);
        $customerRole = Role::query()->where('name', 'customer')->firstOrFail();
        $user->roles()->sync([$customerRole->id]);
        $user->forceFill(['role' => 'customer'])->save();
        app(PermissionResolver::class)->forget($user->fresh());
        $this->assertFalse($user->fresh()->isSuperAdmin());
        $this->assertFalse($user->fresh()->hasPermission('permissions.assign'));
    }

    public function test_legacy_admin_identity_does_not_auto_grant_super_admin(): void
    {
        $user = User::factory()->admin()->create();
        $user->roles()->detach();
        app(PermissionResolver::class)->forget($user->fresh());

        // Materialize via permission resolve (bridge attaches RBAC admin, not super_admin).
        $this->assertTrue($user->fresh()->hasPermission('admin.access'));
        $this->assertFalse($user->fresh()->isSuperAdmin());
        $this->assertContains('admin', $user->fresh()->roleNames());
    }

    public function test_duplicate_bootstrap_is_denied(): void
    {
        $svc = app(SuperAdminBootstrapService::class);
        $svc->create('A', 'first-sa@example.com', 'password123', 'password123');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already exists');
        $svc->create('B', 'second-sa@example.com', 'password123', 'password123');
    }

    public function test_existing_email_is_denied(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->expectException(ValidationException::class);
        app(SuperAdminBootstrapService::class)->create(
            'X',
            'taken@example.com',
            'password123',
            'password123',
        );
    }

    public function test_weak_password_fails_validation(): void
    {
        try {
            app(SuperAdminBootstrapService::class)->create(
                'X',
                'weak@example.com',
                'short',
                'short',
            );
            $this->fail('Expected validation failure');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('password', $e->errors());
        }
    }

    public function test_password_confirmation_mismatch_fails(): void
    {
        try {
            app(SuperAdminBootstrapService::class)->create(
                'X',
                'mismatch@example.com',
                'password123',
                'password999',
            );
            $this->fail('Expected validation failure');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('password', $e->errors());
        }
    }

    public function test_transaction_rolls_back_on_role_assignment_failure(): void
    {
        $this->mock(RoleAssignmentService::class, function ($mock) {
            $mock->shouldReceive('superAdminExists')->andReturn(false);
            $mock->shouldReceive('bootstrapFirstSuperAdmin')
                ->once()
                ->andThrow(new InvalidArgumentException('simulated failure'));
        });

        try {
            app(SuperAdminBootstrapService::class)->create(
                'Rollback',
                'rollback@example.com',
                'password123',
                'password123',
            );
            $this->fail('Expected failure');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('simulated failure', $e->getMessage());
        }

        $this->assertDatabaseMissing('users', ['email' => 'rollback@example.com']);
        $this->assertSame(0, User::query()->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->count());
    }

    public function test_legacy_users_role_cannot_grant_super_admin(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'admin', 'is_active' => true])->save();
        app(PermissionResolver::class)->forget($user);

        // Without RBAC super_admin assignment, privileged permissions must not resolve
        // from users.role alone (legacy bridge maps admin→super_admin ONLY when user_roles empty
        // and materialize/bridge runs — ensure bridge does not apply if we already sync customer).
        $customer = Role::query()->where('name', 'customer')->firstOrFail();
        $user->roles()->sync([$customer->id]);
        app(PermissionResolver::class)->forget($user->fresh());

        $this->assertFalse($user->fresh()->isSuperAdmin());
        $this->assertFalse($user->fresh()->hasPermission('permissions.assign'));
    }

    public function test_super_admin_follows_mfa_enrollment_requirements(): void
    {
        $user = app(SuperAdminBootstrapService::class)->create(
            'MFA Admin',
            'mfa-sa@example.com',
            'password123',
            'password123',
        );

        $this->assertTrue($user->requiresMfaEnrollment());
        $this->assertFalse($user->hasMfaEnabled());

        config(['authorization.mfa.enforce_enrollment' => true]);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('security.mfa.enroll'));
    }

    public function test_audit_event_without_secrets(): void
    {
        $plain = 'password123';
        $user = app(SuperAdminBootstrapService::class)->create(
            'Audited',
            'audit-sa@example.com',
            $plain,
            $plain,
        );

        $event = SecurityEvent::query()->where('event', 'SUPER_ADMIN_BOOTSTRAPPED')->first();
        $this->assertNotNull($event);
        $context = json_encode($event->context);
        $this->assertStringNotContainsString($plain, (string) $context);
        $this->assertStringNotContainsString((string) $user->getAttributes()['password'], (string) $context);
        $this->assertStringNotContainsString('mfa_secret', (string) $context);
        $this->assertStringNotContainsString('recovery', strtolower((string) $context));

        $audit = AuditLog::query()->where('action', 'SUPER_ADMIN_BOOTSTRAPPED')->first();
        $this->assertNotNull($audit);
        $payload = json_encode([$audit->old_values, $audit->new_values]);
        $this->assertStringNotContainsString($plain, (string) $payload);
    }

    public function test_concurrent_bootstrap_only_one_succeeds(): void
    {
        $svc = app(SuperAdminBootstrapService::class);
        $created = 0;
        $denied = 0;

        foreach ([['a@example.com'], ['b@example.com']] as [$email]) {
            try {
                $svc->create('Admin', $email, 'password123', 'password123');
                $created++;
            } catch (InvalidArgumentException) {
                $denied++;
            }
        }

        $this->assertSame(1, $created);
        $this->assertSame(1, $denied);
        $this->assertSame(
            1,
            User::query()->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->count()
        );
    }

    public function test_artisan_command_bootstraps_with_hidden_password(): void
    {
        $this->artisan('admin:create-super-admin', [
            '--name' => 'CLI Admin',
            '--email' => 'cli-sa@example.com',
        ])
            // secret() uses askQuestion under the hood; password is never a CLI option.
            ->expectsQuestion('Password', 'password123')
            ->expectsQuestion('Confirm password', 'password123')
            ->assertSuccessful();

        $user = User::query()->where('email', 'cli-sa@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->isSuperAdmin());

        $this->artisan('admin:create-super-admin', [
            '--name' => 'Another',
            '--email' => 'cli-sa-2@example.com',
        ])
            ->expectsOutputToContain('Bootstrap locked')
            ->assertFailed();
    }

    public function test_bootstrap_does_not_create_authenticated_session(): void
    {
        app(SuperAdminBootstrapService::class)->create(
            'No Session',
            'nosession@example.com',
            'password123',
            'password123',
        );

        $this->assertGuest();
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }
}
