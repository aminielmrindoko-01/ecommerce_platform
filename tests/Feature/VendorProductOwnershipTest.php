<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMarketplace;
use Tests\TestCase;

class VendorProductOwnershipTest extends TestCase
{
    use CreatesMarketplace;
    use RefreshDatabase;

    public function test_vendor_can_create_product_assigned_to_own_store(): void
    {
        [$vendorUser, $vendor] = $this->createVendorUser();

        $response = $this->actingAs($vendorUser)->post(route('vendor.products.store'), [
            'name' => 'Vendor Owned Phone',
            'price' => 25000,
            'stock' => 4,
            'description' => 'Mine',
            'vendor_id' => 9999, // must be ignored
        ]);

        $response->assertRedirect(route('vendor.products.index'));

        $this->assertDatabaseHas('products', [
            'name' => 'Vendor Owned Phone',
            'vendor_id' => $vendor->id,
            'price' => 25000,
        ]);
    }

    public function test_vendor_can_edit_and_delete_own_product(): void
    {
        [$vendorUser, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor, ['name' => 'Editable Item']);

        $this->actingAs($vendorUser)
            ->get(route('vendor.products.edit', $product))
            ->assertOk();

        $this->actingAs($vendorUser)
            ->put(route('vendor.products.update', $product), [
                'name' => 'Updated Item',
                'price' => 12000,
                'stock' => 3,
                'vendor_id' => 9999,
            ])
            ->assertRedirect(route('vendor.products.index'));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Item',
            'vendor_id' => $vendor->id,
        ]);

        $this->actingAs($vendorUser)
            ->delete(route('vendor.products.destroy', $product))
            ->assertRedirect(route('vendor.products.index'));

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_vendor_cannot_access_another_vendors_product(): void
    {
        [$vendorA] = $this->createVendorUser(['email' => 'a@example.com']);
        [, $vendorB] = $this->createVendorUser(['email' => 'b@example.com']);
        $productB = $this->createProductForVendor($vendorB, ['name' => 'B Only']);

        $this->actingAs($vendorA)
            ->get(route('vendor.products.edit', $productB))
            ->assertStatus(403);

        $this->actingAs($vendorA)
            ->put(route('vendor.products.update', $productB), [
                'name' => 'Hijacked',
                'price' => 1,
                'stock' => 1,
            ])
            ->assertStatus(403);

        $this->actingAs($vendorA)
            ->delete(route('vendor.products.destroy', $productB))
            ->assertStatus(403);

        $this->assertDatabaseHas('products', [
            'id' => $productB->id,
            'name' => 'B Only',
            'vendor_id' => $vendorB->id,
        ]);
    }

    public function test_vendor_cannot_change_product_ownership_via_request(): void
    {
        [$vendorA, $storeA] = $this->createVendorUser(['email' => 'ownera@example.com']);
        [, $storeB] = $this->createVendorUser(['email' => 'ownerb@example.com']);
        $product = $this->createProductForVendor($storeA, ['name' => 'Stay With A']);

        $this->actingAs($vendorA)
            ->put(route('vendor.products.update', $product), [
                'name' => 'Stay With A',
                'price' => 15000,
                'stock' => 5,
                'vendor_id' => $storeB->id,
            ])
            ->assertRedirect(route('vendor.products.index'));

        $this->assertSame($storeA->id, (int) $product->fresh()->vendor_id);
    }

    public function test_customer_cannot_use_vendor_product_routes(): void
    {
        $customer = User::factory()->create();
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor);

        $this->actingAs($customer)->get(route('vendor.products.index'))->assertStatus(403);
        $this->actingAs($customer)->get(route('vendor.products.create'))->assertStatus(403);
        $this->actingAs($customer)->post(route('vendor.products.store'), [
            'name' => 'Nope',
            'price' => 10,
            'stock' => 1,
        ])->assertStatus(403);
        $this->actingAs($customer)->get(route('vendor.products.edit', $product))->assertStatus(403);
    }

    public function test_admin_retains_global_product_create(): void
    {
        $admin = User::factory()->admin()->create();
        [, $vendor] = $this->createVendorUser();

        $this->actingAs($admin)->post(route('products.store'), [
            'vendor_id' => $vendor->id,
            'name' => 'Admin Global Product',
            'price' => 5000,
            'stock' => 2,
        ])->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', [
            'name' => 'Admin Global Product',
            'vendor_id' => $vendor->id,
        ]);
    }
}
