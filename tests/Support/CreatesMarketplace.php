<?php

namespace Tests\Support;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;

trait CreatesMarketplace
{
    /**
     * Create a vendor user linked to a store (1:1).
     *
     * @param  array<string, mixed>  $userOverrides
     * @param  array<string, mixed>  $vendorOverrides
     * @return array{0: User, 1: Vendor}
     */
    protected function createVendorUser(array $userOverrides = [], array $vendorOverrides = []): array
    {
        $user = User::factory()->vendor()->create($userOverrides);

        $vendor = Vendor::create(array_merge([
            'store_name' => 'Store '.$user->id,
            'email' => 'store'.$user->id.'@example.com',
            'is_verified' => true,
        ], $vendorOverrides));

        $vendor->user_id = $user->id;
        $vendor->save();

        return [$user->fresh(), $vendor->fresh()];
    }

    /**
     * Create a product owned by the given vendor (vendor_id set explicitly).
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function createProductForVendor(Vendor $vendor, array $overrides = []): Product
    {
        $category = Category::first() ?? Category::create([
            'name' => 'Gadgets',
            'slug' => 'gadgets-'.uniqid(),
        ]);

        $product = new Product(array_merge([
            'category_id' => $category->id,
            'name' => 'Test Product',
            'slug' => 'test-product-'.uniqid(),
            'price' => 10000,
            'stock' => 10,
            'description' => 'Test product',
        ], $overrides));

        $product->vendor_id = $vendor->id;
        $product->save();

        return $product->fresh();
    }
}
