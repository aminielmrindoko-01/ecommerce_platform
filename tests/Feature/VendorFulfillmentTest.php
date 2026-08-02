<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\OrderItemFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Support\CreatesMarketplace;
use Tests\TestCase;

class VendorFulfillmentTest extends TestCase
{
    use CreatesMarketplace;
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Order, 2: OrderItem, 3: \App\Models\Product}
     */
    protected function createOwnedOrderItem(array $shipping = []): array
    {
        [$vendorUser, $store] = $this->createVendorUser(['email' => 'owner-'.uniqid().'@example.com']);
        $product = $this->createProductForVendor($store, ['name' => 'Owned Widget', 'price' => 2500]);
        $customer = User::factory()->create(['email' => 'buyer-'.uniqid().'@example.com']);

        $order = Order::create([
            'order_number' => 'SN-FUL-'.uniqid(),
            'user_id' => $customer->id,
            'total_price' => 5000,
            'status' => 'pending',
            'payment_method' => 'cod',
            'shipping_method' => 'standard',
            'shipping_address' => array_merge([
                'full_name' => 'Buyer Name',
                'phone' => '+255700000001',
                'line1' => '1 Market Rd',
                'line2' => '',
                'city' => 'Dar es Salaam',
                'region' => 'Dar',
            ], $shipping),
        ]);

        $item = OrderItem::recordPurchase($order->id, $product->id, 2, 2500);

        return [$vendorUser, $order->fresh(), $item->fresh(), $product];
    }

    protected function patchFulfillment(User $actor, Order $order, OrderItem $item, string $status, array $extra = [])
    {
        return $this->actingAs($actor)->patch(
            route('vendor.orders.items.fulfillment', [$order, $item]),
            array_merge(['fulfillment_status' => $status], $extra)
        );
    }

    public function test_vendor_can_access_fulfillment_order_detail(): void
    {
        [$vendor, $order, $item] = $this->createOwnedOrderItem();

        $this->actingAs($vendor)
            ->get(route('vendor.orders.show', $order))
            ->assertOk()
            ->assertSee('Owned Widget')
            ->assertSee('Pending')
            ->assertSee('Buyer Name')
            ->assertSee('+255700000001')
            ->assertDontSee($order->user->email);
    }

    public function test_customer_cannot_access_vendor_fulfillment_routes(): void
    {
        [, $order, $item] = $this->createOwnedOrderItem();
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->get(route('vendor.orders.show', $order))
            ->assertStatus(403);

        $this->patchFulfillment($customer, $order, $item, 'confirmed')
            ->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_fulfillment(): void
    {
        [, $order, $item] = $this->createOwnedOrderItem();

        $this->get(route('vendor.orders.show', $order))
            ->assertRedirect(route('login'));

        $this->patch(
            route('vendor.orders.items.fulfillment', [$order, $item]),
            ['fulfillment_status' => 'confirmed']
        )->assertRedirect(route('login'));
    }

    public function test_admin_cannot_use_vendor_fulfillment_route_but_can_view_admin_orders(): void
    {
        [$vendor, $order, $item] = $this->createOwnedOrderItem();
        $admin = User::factory()->admin()->create();

        $this->patchFulfillment($admin, $order, $item, 'confirmed')
            ->assertStatus(403);

        $this->assertSame('pending', $item->fresh()->fulfillment_status);

        $this->actingAs($admin)
            ->get(route('admin.orders'))
            ->assertOk()
            ->assertSee('Item fulfillment')
            ->assertSee('Owned Widget')
            ->assertSee('Pending');
    }

    public function test_admin_policy_allows_fulfillment_update(): void
    {
        [, , $item] = $this->createOwnedOrderItem();
        $admin = User::factory()->admin()->create();

        $this->assertTrue($admin->can('updateFulfillment', $item));
        $this->assertTrue($admin->can('view', $item));
    }

    public function test_vendor_can_modify_own_order_item_fulfillment(): void
    {
        [$vendor, $order, $item] = $this->createOwnedOrderItem();

        $this->patchFulfillment($vendor, $order, $item, 'confirmed')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('confirmed', $item->fresh()->fulfillment_status);
    }

    public function test_vendor_cannot_modify_another_vendors_order_item(): void
    {
        [$vendorA, $order, $itemA] = $this->createOwnedOrderItem();
        [$vendorB, $storeB] = $this->createVendorUser(['email' => 'vb-idor@example.com']);
        $productB = $this->createProductForVendor($storeB, ['name' => 'B Only Product', 'price' => 9000]);
        $itemB = OrderItem::recordPurchase($order->id, $productB->id, 1, 9000);

        $this->patchFulfillment($vendorA, $order, $itemB, 'confirmed')
            ->assertStatus(403);

        $this->assertSame('pending', $itemB->fresh()->fulfillment_status);

        $this->actingAs($vendorA)
            ->get(route('vendor.orders.show', $order))
            ->assertOk()
            ->assertSee('Owned Widget')
            ->assertDontSee('B Only Product');
    }

    public function test_vendor_cannot_access_unrelated_order_item_by_swapping_url_ids(): void
    {
        [$vendorA, $orderA, $itemA] = $this->createOwnedOrderItem();
        [$vendorB, $orderB, $itemB] = $this->createOwnedOrderItem();

        // Wrong order + foreign item
        $this->patchFulfillment($vendorA, $orderB, $itemB, 'confirmed')
            ->assertStatus(403);

        // Own order id with foreign item id
        $this->patchFulfillment($vendorA, $orderA, $itemB, 'confirmed')
            ->assertStatus(403);

        // Foreign order id with own item id
        $this->patchFulfillment($vendorA, $orderB, $itemA, 'confirmed')
            ->assertStatus(403);

        $this->assertSame('pending', $itemA->fresh()->fulfillment_status);
        $this->assertSame('pending', $itemB->fresh()->fulfillment_status);
    }

    public function test_fulfillment_endpoint_ignores_ownership_and_price_tampering(): void
    {
        [$vendor, $order, $item, $product] = $this->createOwnedOrderItem();
        $originalPrice = (string) $item->price;
        $originalQty = (int) $item->quantity;
        $originalProductId = (int) $item->product_id;
        $originalOrderId = (int) $item->order_id;
        $originalOrderTotal = (string) $order->total_price;

        $this->patchFulfillment($vendor, $order, $item, 'confirmed', [
            'price' => 1,
            'quantity' => 999,
            'product_id' => 99999,
            'order_id' => 99999,
            'vendor_id' => 99999,
            'user_id' => 99999,
            'customer_id' => 99999,
            'total_price' => 1,
        ])->assertRedirect()->assertSessionHas('success');

        $fresh = $item->fresh();
        $this->assertSame('confirmed', $fresh->fulfillment_status);
        $this->assertSame($originalPrice, (string) $fresh->price);
        $this->assertSame($originalQty, (int) $fresh->quantity);
        $this->assertSame($originalProductId, (int) $fresh->product_id);
        $this->assertSame($originalOrderId, (int) $fresh->order_id);
        $this->assertSame($originalOrderTotal, (string) $order->fresh()->total_price);
        $this->assertSame($product->vendor_id, $product->fresh()->vendor_id);
    }

    public function test_legal_state_transitions(): void
    {
        $service = app(OrderItemFulfillmentService::class);

        [, , $happyPath] = $this->createOwnedOrderItem();
        $service->transition($happyPath, 'confirmed');
        $this->assertSame('confirmed', $happyPath->fresh()->fulfillment_status);
        $service->transition($happyPath->fresh(), 'processing');
        $this->assertSame('processing', $happyPath->fresh()->fulfillment_status);
        $service->transition($happyPath->fresh(), 'shipped');
        $this->assertSame('shipped', $happyPath->fresh()->fulfillment_status);
        $service->transition($happyPath->fresh(), 'delivered');
        $this->assertSame('delivered', $happyPath->fresh()->fulfillment_status);

        [, , $cancelFromPending] = $this->createOwnedOrderItem();
        $service->transition($cancelFromPending, 'cancelled');
        $this->assertSame('cancelled', $cancelFromPending->fresh()->fulfillment_status);

        [, , $cancelFromConfirmed] = $this->createOwnedOrderItem();
        $service->transition($cancelFromConfirmed, 'confirmed');
        $service->transition($cancelFromConfirmed->fresh(), 'cancelled');
        $this->assertSame('cancelled', $cancelFromConfirmed->fresh()->fulfillment_status);
    }

    public function test_illegal_state_transitions_are_rejected(): void
    {
        $service = app(OrderItemFulfillmentService::class);

        $cases = [
            ['delivered', 'pending'],
            ['delivered', 'shipped'],
            ['cancelled', 'processing'],
            ['cancelled', 'pending'],
            ['shipped', 'pending'],
            ['shipped', 'confirmed'],
            ['processing', 'confirmed'],
        ];

        foreach ($cases as [$from, $to]) {
            [, , $item] = $this->createOwnedOrderItem();
            $item->fulfillment_status = $from;
            $item->save();

            try {
                $service->transition($item->fresh(), $to);
                $this->fail("Expected invalid transition {$from} → {$to} to throw.");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('Cannot transition', $e->getMessage());
            }

            $this->assertSame($from, $item->fresh()->fulfillment_status);
        }
    }

    public function test_vendor_http_rejects_illegal_transition(): void
    {
        [$vendor, $order, $item] = $this->createOwnedOrderItem();
        $item->fulfillment_status = 'delivered';
        $item->save();

        $this->patchFulfillment($vendor, $order, $item, 'pending')
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('delivered', $item->fresh()->fulfillment_status);
    }

    public function test_customer_can_see_fulfillment_status_grouped_by_vendor(): void
    {
        [$vendorA, $storeA] = $this->createVendorUser(['email' => 'cust-a@example.com'], ['store_name' => 'Alpha Store']);
        [$vendorB, $storeB] = $this->createVendorUser(['email' => 'cust-b@example.com'], ['store_name' => 'Beta Store']);
        $productA = $this->createProductForVendor($storeA, ['name' => 'Alpha Laptop', 'price' => 1000]);
        $productB = $this->createProductForVendor($storeB, ['name' => 'Beta Shoes', 'price' => 2000]);
        $customer = User::factory()->create();

        $order = Order::create([
            'order_number' => 'SN-CUST-VIEW',
            'user_id' => $customer->id,
            'total_price' => 4000,
            'status' => 'paid',
        ]);
        $itemA = OrderItem::recordPurchase($order->id, $productA->id, 1, 1000);
        $itemB = OrderItem::recordPurchase($order->id, $productB->id, 1, 2000);

        app(OrderItemFulfillmentService::class)->transition($itemA, 'confirmed');
        app(OrderItemFulfillmentService::class)->transition($itemA->fresh(), 'processing');
        app(OrderItemFulfillmentService::class)->transition($itemA->fresh(), 'shipped');
        app(OrderItemFulfillmentService::class)->transition($itemB, 'confirmed');

        $response = $this->actingAs($customer)->get(route('account.orders.show', $order));

        $response->assertOk();
        $response->assertSee('Alpha Store');
        $response->assertSee('Beta Store');
        $response->assertSee('Alpha Laptop');
        $response->assertSee('Beta Shoes');
        $response->assertSee('Shipped');
        $response->assertSee('Confirmed');
        $response->assertDontSee(route('vendor.orders.items.fulfillment', [$order, $itemA], false));
    }

    public function test_customer_has_no_fulfillment_update_route_access(): void
    {
        [, $order, $item] = $this->createOwnedOrderItem();
        $owner = User::findOrFail($order->user_id);

        $this->patchFulfillment($owner, $order, $item, 'confirmed')
            ->assertStatus(403);

        $this->assertSame('pending', $item->fresh()->fulfillment_status);
    }

    public function test_dashboard_fulfillment_kpis_count_only_own_items(): void
    {
        [$vendorA, $storeA] = $this->createVendorUser(['email' => 'kpi-a@example.com']);
        [, $storeB] = $this->createVendorUser(['email' => 'kpi-b@example.com']);
        $productA = $this->createProductForVendor($storeA, ['name' => 'KPI A', 'price' => 100]);
        $productB = $this->createProductForVendor($storeB, ['name' => 'KPI B', 'price' => 100]);
        $customer = User::factory()->create();

        $order = Order::create([
            'order_number' => 'SN-KPI',
            'user_id' => $customer->id,
            'total_price' => 300,
            'status' => 'pending',
        ]);
        $itemA1 = OrderItem::recordPurchase($order->id, $productA->id, 1, 100);
        $itemA2 = OrderItem::recordPurchase($order->id, $productA->id, 1, 100);
        $itemB = OrderItem::recordPurchase($order->id, $productB->id, 1, 100);

        app(OrderItemFulfillmentService::class)->transition($itemA1, 'confirmed');
        $itemB->fulfillment_status = 'shipped';
        $itemB->save();

        $this->actingAs($vendorA)
            ->get(route('vendor.dashboard'))
            ->assertOk()
            ->assertSee('Fulfillment')
            ->assertSee('Pending')
            ->assertSee('Confirmed');

        $pending = OrderItem::query()
            ->whereHas('product', fn ($q) => $q->where('vendor_id', $storeA->id))
            ->where('fulfillment_status', 'pending')
            ->count();
        $confirmed = OrderItem::query()
            ->whereHas('product', fn ($q) => $q->where('vendor_id', $storeA->id))
            ->where('fulfillment_status', 'confirmed')
            ->count();

        $this->assertSame(1, $pending);
        $this->assertSame(1, $confirmed);
        $this->assertSame(0, OrderItem::query()
            ->whereHas('product', fn ($q) => $q->where('vendor_id', $storeA->id))
            ->where('fulfillment_status', 'shipped')
            ->count());
        $this->assertSame('confirmed', $itemA1->fresh()->fulfillment_status);
        $this->assertSame('pending', $itemA2->fresh()->fulfillment_status);
    }

    public function test_new_order_items_default_to_pending_fulfillment(): void
    {
        [, , $item] = $this->createOwnedOrderItem();

        $this->assertSame('pending', $item->fulfillment_status);
        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::hasColumn('order_items', 'fulfillment_status')
        );
    }
}
