<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function seedCatalog(): Product
    {
        $vendor = Vendor::create([
            'store_name' => 'Test Store',
            'email' => 'store@example.com',
            'is_verified' => true,
        ]);

        $category = Category::create([
            'name' => 'Gadgets',
            'slug' => 'gadgets',
        ]);

        return Product::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Secure Phone',
            'slug' => 'secure-phone',
            'price' => 100000,
            'stock' => 5,
            'description' => 'A test product',
        ]);
    }

    public function test_customer_cannot_access_product_create_form(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->get(route('products.create'))
            ->assertStatus(403);
    }

    public function test_customer_cannot_create_products(): void
    {
        $customer = User::factory()->create();
        $product = $this->seedCatalog();

        $response = $this->actingAs($customer)->post(route('products.store'), [
            'vendor_id' => $product->vendor_id,
            'category_id' => $product->category_id,
            'name' => 'Hijacked Product',
            'price' => 1,
            'stock' => 999,
            'description' => 'Should not be created',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('products', ['name' => 'Hijacked Product']);
    }

    public function test_customer_cannot_edit_or_delete_existing_product(): void
    {
        $customer = User::factory()->create();
        $product = $this->seedCatalog();

        $this->actingAs($customer)
            ->get(route('products.edit', $product->id))
            ->assertStatus(403);

        $this->actingAs($customer)
            ->put(route('products.update', $product->id), [
                'vendor_id' => $product->vendor_id,
                'category_id' => $product->category_id,
                'name' => 'Tampered Name',
                'price' => 1,
                'stock' => 999,
            ])
            ->assertStatus(403);

        $this->actingAs($customer)
            ->delete(route('products.destroy', $product->id))
            ->assertStatus(403);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Secure Phone',
            'price' => 100000,
        ]);
    }

    public function test_admin_can_create_product(): void
    {
        $admin = User::factory()->admin()->create();
        $existing = $this->seedCatalog();

        $response = $this->actingAs($admin)->post(route('products.store'), [
            'vendor_id' => $existing->vendor_id,
            'category_id' => $existing->category_id,
            'name' => 'Admin Created Product',
            'price' => 25000,
            'stock' => 10,
            'description' => 'Created by admin',
        ]);

        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', ['name' => 'Admin Created Product']);
    }
}
