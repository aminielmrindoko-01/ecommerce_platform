<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_view_own_order_but_not_others(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $vendor = Vendor::create([
            'store_name' => 'Order Vendor',
            'email' => 'order-vendor@example.com',
        ]);

        $product = Product::create([
            'vendor_id' => $vendor->id,
            'name' => 'Owned Item',
            'slug' => 'owned-item',
            'price' => 1000,
            'stock' => 5,
        ]);

        $order = Order::create([
            'order_number' => 'SN-OWNED',
            'user_id' => $owner->id,
            'total_price' => 1000,
            'status' => 'pending',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 1000,
        ]);

        $this->actingAs($owner)
            ->get(route('account.orders.show', $order))
            ->assertOk();

        $this->actingAs($other)
            ->get(route('account.orders.show', $order))
            ->assertStatus(403);
    }
}
