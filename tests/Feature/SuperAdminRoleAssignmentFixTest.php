<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\RoleAssignmentService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminRoleAssignmentFixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    protected function assign(User $user, string $rbac, string $legacy = 'admin'): User
    {
        $user->forceFill(['role' => $legacy, 'is_active' => true])->save();
        $role = Role::query()->where('name', $rbac)->firstOrFail();
        $user->roles()->sync([$role->id]);
        app(PermissionResolver::class)->forget($user);

        return $user->fresh();
    }

    public function test_legacy_admin_bridge_maps_to_admin_not_super_admin(): void
    {
        $user = User::factory()->admin()->create();
        $user->roles()->detach();
        app(PermissionResolver::class)->forget($user);

        $this->assertTrue($user->fresh()->hasPermission('admin.access'));
        $this->assertFalse($user->fresh()->isSuperAdmin());
        $this->assertContains('admin', $user->fresh()->roleNames());
    }

    public function test_reconcile_command_keeps_one_super_admin_and_demotes_others(): void
    {
        $keep = $this->assign(User::factory()->create([
            'name' => 'Aminieli Mrindoko',
            'email' => 'admin@gmail.com',
        ]), 'super_admin', 'admin');

        $other = $this->assign(User::factory()->create([
            'name' => 'Administrator',
            'email' => 'admin2@example.com',
        ]), 'super_admin', 'admin');

        $this->artisan('admin:reconcile-super-admins', [
            '--keep' => 'admin@gmail.com',
            '--force' => true,
        ])->assertSuccessful();

        $this->assertTrue($keep->fresh()->isSuperAdmin());
        $this->assertFalse($other->fresh()->isSuperAdmin());
        $this->assertContains('admin', $other->fresh()->roleNames());
        $this->assertSame('admin', $other->fresh()->role);

        $this->assertSame(
            1,
            User::query()->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->count()
        );
    }

    public function test_normal_admin_cannot_assign_super_admin(): void
    {
        $admin = $this->assign(User::factory()->create(['email' => 'ops@example.com']), 'admin', 'admin');
        $target = $this->assign(User::factory()->create(['email' => 'staff@example.com']), 'customer', 'customer');

        $this->actingAs($admin)
            ->withSession(['auth.step_up_confirmed_at' => now()->timestamp])
            ->put(route('admin.users.update', $target->id), [
                'role' => 'admin',
                'rbac_role' => 'super_admin',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertFalse($target->fresh()->isSuperAdmin());
    }

    public function test_super_admin_can_assign_admin_role(): void
    {
        $super = $this->assign(User::factory()->create(['email' => 'sa@example.com']), 'super_admin', 'admin');
        $target = $this->assign(User::factory()->create(['email' => 'promote@example.com']), 'customer', 'customer');

        $this->actingAs($super)
            ->withSession(['auth.step_up_confirmed_at' => now()->timestamp])
            ->put(route('admin.users.update', $target->id), [
                'role' => 'admin',
                'rbac_role' => 'admin',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFalse($target->fresh()->isSuperAdmin());
        $this->assertContains('admin', $target->fresh()->roleNames());
    }

    public function test_user_cannot_change_own_role(): void
    {
        $super = $this->assign(User::factory()->create(), 'super_admin', 'admin');

        $this->actingAs($super)
            ->withSession(['auth.step_up_confirmed_at' => now()->timestamp])
            ->put(route('admin.users.update', $super->id), [
                'role' => 'customer',
                'rbac_role' => 'customer',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertTrue($super->fresh()->isSuperAdmin());
    }

    public function test_normal_admin_does_not_receive_super_admin_star_permissions(): void
    {
        $admin = $this->assign(User::factory()->create(), 'admin', 'admin');

        $this->assertTrue($admin->hasPermission('admin.access'));
        $this->assertTrue($admin->hasPermission('users.update'));
        $this->assertFalse($admin->hasPermission('permissions.assign'));
        $this->assertFalse($admin->isSuperAdmin());
    }

    public function test_bootstrap_remains_locked_when_super_admin_exists(): void
    {
        $this->assign(User::factory()->create(['email' => 'only-sa@example.com']), 'super_admin', 'admin');

        $this->artisan('admin:create-super-admin', [
            '--name' => 'Another',
            '--email' => 'another-sa@example.com',
        ])
            ->expectsOutputToContain('Bootstrap locked')
            ->assertFailed();
    }

    public function test_empty_rbac_role_fallback_does_not_promote_to_super_admin(): void
    {
        $super = $this->assign(User::factory()->create(), 'super_admin', 'admin');
        $target = $this->assign(User::factory()->create(), 'customer', 'customer');

        $this->actingAs($super)
            ->withSession(['auth.step_up_confirmed_at' => now()->timestamp])
            ->put(route('admin.users.update', $target->id), [
                'role' => 'admin',
                // omit rbac_role → must fall back to admin, not super_admin
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFalse($target->fresh()->isSuperAdmin());
        $this->assertContains('admin', $target->fresh()->roleNames());
    }
}
