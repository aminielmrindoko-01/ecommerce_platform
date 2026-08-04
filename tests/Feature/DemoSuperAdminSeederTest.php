<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSuperAdminSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoSuperAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_super_admin_seeder_creates_active_rbac_super_admin(): void
    {
        $this->seed(RbacSeeder::class);
        $this->seed(DemoSuperAdminSeeder::class);

        $user = User::query()->where('email', DemoSuperAdminSeeder::EMAIL)->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->isActiveAccount());
        $this->assertTrue($user->isSuperAdmin());
        $this->assertContains('super_admin', $user->roleNames());
        $this->assertTrue($user->hasPermission('admin.access'));
        $this->assertTrue($user->hasPermission('permissions.assign'));
        $this->assertTrue(Hash::check(DemoSuperAdminSeeder::DEV_PASSWORD, $user->getAttributes()['password']));
        $this->assertNotSame(DemoSuperAdminSeeder::DEV_PASSWORD, $user->getAttributes()['password']);
    }

    public function test_demo_super_admin_seeder_is_idempotent_and_does_not_duplicate(): void
    {
        $this->seed(DemoSuperAdminSeeder::class);
        $this->seed(DemoSuperAdminSeeder::class);

        $this->assertSame(
            1,
            User::query()->where('email', DemoSuperAdminSeeder::EMAIL)->count()
        );
    }
}
