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

        $vendor = new Vendor(array_merge([
            'store_name' => 'Store '.$user->id,
            'email' => 'store'.$user->id.'@example.com',
        ], collect($vendorOverrides)->except(['is_verified', 'rating_avg', 'user_id'])->all()));
        $trust = [
            'user_id' => $user->id,
            'is_verified' => (bool) ($vendorOverrides['is_verified'] ?? true),
        ];
        if (array_key_exists('rating_avg', $vendorOverrides)) {
            $trust['rating_avg'] = $vendorOverrides['rating_avg'];
        }
        $vendor->forceFill($trust)->save();

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
            'description' => 'Test product',
        ], collect($overrides)->except(['stock', 'status', 'vendor_id', 'reorder_level', 'reserved_quantity'])->all()));

        $product->vendor_id = $vendor->id;
        $product->forceFill([
            'stock' => (int) ($overrides['stock'] ?? 10),
            'status' => $overrides['status'] ?? Product::STATUS_PUBLISHED,
            'published_at' => now(),
            'reorder_level' => (int) ($overrides['reorder_level'] ?? 5),
            'reserved_quantity' => (int) ($overrides['reserved_quantity'] ?? 0),
        ])->save();

        return $product->fresh();
    }
}
