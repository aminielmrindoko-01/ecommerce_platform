<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMarketplace;
use Tests\TestCase;

class VendorOrderIsolationTest extends TestCase
{
    use CreatesMarketplace;
    use RefreshDatabase;

    public function test_vendor_sees_only_own_line_items_and_subtotal(): void
    {
        [$vendorA, $storeA] = $this->createVendorUser(['email' => 'va@example.com']);
        [, $storeB] = $this->createVendorUser(['email' => 'vb@example.com']);

        $productA = $this->createProductForVendor($storeA, ['name' => 'A Item', 'price' => 1000]);
        $productB = $this->createProductForVendor($storeB, ['name' => 'B Item', 'price' => 5000]);

        $customer = User::factory()->create();
        $order = Order::create([
            'order_number' => 'SN-MULTI',
            'user_id' => $customer->id,
            'total_price' => 7000,
            'status' => 'pending',
            'payment_method' => 'cod',
            'shipping_method' => 'pickup',
        ]);
        $order->items()->create(['product_id' => $productA->id, 'quantity' => 2, 'price' => 1000]);
        $order->items()->create(['product_id' => $productB->id, 'quantity' => 1, 'price' => 5000]);

        $response = $this->actingAs($vendorA)->get(route('vendor.orders.show', $order));

        $response->assertOk();
        $response->assertSee('A Item');
        $response->assertDontSee('B Item');
        $response->assertSee('2,000'); // vendor subtotal 2 * 1000
        $response->assertDontSee('7,000'); // full order total must not be presented as vendor sales
    }

    public function test_vendor_cannot_view_order_without_their_products(): void
    {
        [$vendorA] = $this->createVendorUser(['email' => 'va2@example.com']);
        [, $storeB] = $this->createVendorUser(['email' => 'vb2@example.com']);
        $productB = $this->createProductForVendor($storeB, ['name' => 'Only B']);

        $customer = User::factory()->create();
        $order = Order::create([
            'order_number' => 'SN-BONLY',
            'user_id' => $customer->id,
            'total_price' => 5000,
            'status' => 'pending',
        ]);
        $order->items()->create(['product_id' => $productB->id, 'quantity' => 1, 'price' => 5000]);

        $this->actingAs($vendorA)
            ->get(route('vendor.orders.show', $order))
            ->assertStatus(403);
    }

    public function test_customer_cannot_access_vendor_orders(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->get(route('vendor.orders.index'))
            ->assertStatus(403);
    }
}
