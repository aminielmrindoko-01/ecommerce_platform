<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertStatus(200);
    }

    public function test_non_admin_is_forbidden_from_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertStatus(403);
    }

    public function test_guest_is_redirected_from_admin(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_customer_cannot_update_user_roles(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $target = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)->put("/admin/users/{$target->id}", [
            'role' => 'admin',
        ]);

        $response->assertStatus(403);
        $this->assertSame('customer', $target->fresh()->role);
    }

    public function test_admin_can_update_user_roles(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($admin)
            ->withSession(['auth.step_up_confirmed_at' => now()->timestamp])
            ->put("/admin/users/{$target->id}", [
                'role' => 'vendor',
            ]);

        $response->assertRedirect();
        $this->assertSame('vendor', $target->fresh()->role);
    }
}
