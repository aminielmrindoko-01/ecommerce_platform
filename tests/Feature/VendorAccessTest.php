<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMarketplace;
use Tests\TestCase;

class VendorAccessTest extends TestCase
{
    use CreatesMarketplace;
    use RefreshDatabase;

    public function test_vendor_can_access_dashboard(): void
    {
        [$vendorUser] = $this->createVendorUser();

        $this->actingAs($vendorUser)
            ->get(route('vendor.dashboard'))
            ->assertOk();
    }

    public function test_vendor_login_redirects_to_dashboard(): void
    {
        [$vendorUser] = $this->createVendorUser([
            'email' => 'vendor-login@example.com',
            'password' => 'password123',
        ]);

        $this->post(route('login.submit'), [
            'email' => 'vendor-login@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('vendor.dashboard'));
    }

    public function test_customer_cannot_access_vendor_dashboard(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)
            ->get(route('vendor.dashboard'))
            ->assertStatus(403);
    }

    public function test_admin_cannot_access_vendor_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('vendor.dashboard'))
            ->assertStatus(403);
    }

    public function test_vendor_without_store_cannot_access_dashboard(): void
    {
        $orphanVendor = User::factory()->vendor()->create();

        $this->actingAs($orphanVendor)
            ->get(route('vendor.dashboard'))
            ->assertStatus(403);
    }

    public function test_guest_is_redirected_from_vendor_routes(): void
    {
        $this->get(route('vendor.dashboard'))->assertRedirect(route('login'));
    }
}
