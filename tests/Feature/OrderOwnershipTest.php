<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMarketplace;
use Tests\TestCase;

class OrderOwnershipTest extends TestCase
{
    use CreatesMarketplace;
    use RefreshDatabase;

    public function test_customer_can_view_own_order_but_not_others(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor, ['name' => 'Owned Item', 'price' => 1000]);

        $order = Order::create([
            'order_number' => 'SN-OWNED',
            'user_id' => $owner->id,
            'total_price' => 1000,
            'status' => 'pending',
        ]);

        OrderItem::recordPurchase($order->id, $product->id, 1, 1000);

        $this->actingAs($owner)
            ->get(route('account.orders.show', $order))
            ->assertOk();

        $this->actingAs($other)
            ->get(route('account.orders.show', $order))
            ->assertStatus(403);
    }
}
