<?php

namespace Tests\Feature;

use App\Events\OrderPlaced;
use App\Models\FulfillmentStatusHistory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Notifications\CustomerOrderItemFulfillmentUpdated;
use App\Notifications\VendorNewOrderReceived;
use App\Notifications\VendorOrderItemCancelled;
use App\Services\OrderFulfillmentSummary;
use App\Services\OrderItemFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Tests\Support\CreatesMarketplace;
use Tests\TestCase;

class MarketplaceOperationsTest extends TestCase
{
    use CreatesMarketplace;
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Order, 2: OrderItem, 3: User}
     */
    protected function ownedItem(): array
    {
        [$vendorUser, $store] = $this->createVendorUser(['email' => 'ops-v-'.uniqid().'@example.com']);
        $product = $this->createProductForVendor($store, ['name' => 'Ops Widget', 'price' => 1500]);
        $customer = User::factory()->create(['email' => 'ops-c-'.uniqid().'@example.com']);

        $order = Order::create([
            'order_number' => 'SN-OPS-'.uniqid(),
            'user_id' => $customer->id,
            'total_price' => 1500,
            'status' => 'pending',
            'payment_method' => 'cod',
            'shipping_method' => 'pickup',
        ]);

        $item = OrderItem::recordPurchase($order->id, $product->id, 1, 1500);

        return [$vendorUser, $order->fresh(), $item->fresh(), $customer];
    }

    public function test_customer_receives_fulfillment_notification(): void
    {
        Notification::fake();
        [$vendor, , $item, $customer] = $this->ownedItem();
        $service = app(OrderItemFulfillmentService::class);

        $service->transition($item, 'confirmed', $vendor, 'vendor');

        Notification::assertSentTo($customer, CustomerOrderItemFulfillmentUpdated::class);
    }

    public function test_vendor_receives_new_order_notification_once_per_vendor(): void
    {
        Notification::fake();

        [$vendorA, $storeA] = $this->createVendorUser(['email' => 'new-a@example.com']);
        [$vendorB, $storeB] = $this->createVendorUser(['email' => 'new-b@example.com']);
        $productA1 = $this->createProductForVendor($storeA, ['name' => 'A1']);
        $productA2 = $this->createProductForVendor($storeA, ['name' => 'A2']);
        $productB = $this->createProductForVendor($storeB, ['name' => 'B1']);
        $customer = User::factory()->create();

        $order = Order::create([
            'order_number' => 'SN-NEW',
            'user_id' => $customer->id,
            'total_price' => 3000,
            'status' => 'pending',
        ]);
        OrderItem::recordPurchase($order->id, $productA1->id, 1, 1000);
        OrderItem::recordPurchase($order->id, $productA2->id, 1, 1000);
        OrderItem::recordPurchase($order->id, $productB->id, 1, 1000);

        OrderPlaced::dispatch($order->fresh()->load('items.product.vendor.user'));

        Notification::assertSentTo($vendorA, VendorNewOrderReceived::class, function ($n) {
            return $n->itemCount === 2;
        });
        Notification::assertSentTo($vendorB, VendorNewOrderReceived::class, function ($n) {
            return $n->itemCount === 1;
        });
        Notification::assertSentTimes(VendorNewOrderReceived::class, 2);
    }

    public function test_vendor_receives_cancellation_notification(): void
    {
        Notification::fake();
        [$vendor, , $item] = $this->ownedItem();

        app(OrderItemFulfillmentService::class)->transition($item, 'cancelled', $vendor, 'vendor');

        Notification::assertSentTo($vendor, VendorOrderItemCancelled::class);
    }

    public function test_customer_cannot_mark_another_users_notification_read(): void
    {
        [$vendor, , $item, $customerA] = $this->ownedItem();
        app(OrderItemFulfillmentService::class)->transition($item, 'confirmed', $vendor, 'vendor');

        $notification = $customerA->notifications()->first();
        $this->assertNotNull($notification);

        $customerB = User::factory()->create();

        $this->actingAs($customerB)
            ->post(route('account.notifications.read', $notification->id))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_notifications_page_only_lists_own_notifications(): void
    {
        [$vendorA, , $itemA, $customerA] = $this->ownedItem();
        [$vendorB, , $itemB, $customerB] = $this->ownedItem();

        app(OrderItemFulfillmentService::class)->transition($itemA, 'confirmed', $vendorA, 'vendor');
        app(OrderItemFulfillmentService::class)->transition($itemB, 'confirmed', $vendorB, 'vendor');

        $this->assertSame(1, $customerA->notifications()->count());
        $this->assertSame(1, $customerB->notifications()->count());
        $this->assertSame(
            $itemA->id,
            $customerA->notifications()->first()->data['order_item_id'] ?? null
        );
        $this->assertNotSame(
            $itemB->id,
            $customerA->notifications()->first()->data['order_item_id'] ?? null
        );

        $this->actingAs($customerA)
            ->get(route('account.notifications'))
            ->assertOk()
            ->assertSee('Ops Widget');
    }

    public function test_admin_can_perform_permitted_override_with_audit(): void
    {
        [, $order, $item] = $this->ownedItem();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('admin.orders.items.fulfillment', [$order, $item]), [
                'fulfillment_status' => 'confirmed',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('confirmed', $item->fresh()->fulfillment_status);

        $history = FulfillmentStatusHistory::where('order_item_id', $item->id)->first();
        $this->assertNotNull($history);
        $this->assertSame($admin->id, $history->actor_user_id);
        $this->assertSame('admin', $history->actor_role);
        $this->assertSame('pending', $history->from_status);
        $this->assertSame('confirmed', $history->to_status);
    }

    public function test_admin_cancellation_from_processing_requires_reason(): void
    {
        [, $order, $item] = $this->ownedItem();
        $admin = User::factory()->admin()->create();
        $item->fulfillment_status = 'processing';
        $item->save();

        $this->actingAs($admin)
            ->from(route('admin.orders'))
            ->patch(route('admin.orders.items.fulfillment', [$order, $item]), [
                'fulfillment_status' => 'cancelled',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertSame('processing', $item->fresh()->fulfillment_status);
        $this->assertSame(0, FulfillmentStatusHistory::where('order_item_id', $item->id)->count());

        $this->actingAs($admin)
            ->patch(route('admin.orders.items.fulfillment', [$order, $item]), [
                'fulfillment_status' => 'cancelled',
                'reason' => 'Carrier failed pickup',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $history = FulfillmentStatusHistory::where('order_item_id', $item->id)->latest('id')->first();
        $this->assertSame('Carrier failed pickup', $history->reason);
        $this->assertSame('cancelled', $item->fresh()->fulfillment_status);
    }

    public function test_admin_reopen_requires_reason(): void
    {
        [, $order, $item] = $this->ownedItem();
        $admin = User::factory()->admin()->create();
        $item->fulfillment_status = 'cancelled';
        $item->save();

        $this->actingAs($admin)
            ->from(route('admin.orders'))
            ->patch(route('admin.orders.items.fulfillment', [$order, $item]), [
                'fulfillment_status' => 'pending',
            ])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)
            ->patch(route('admin.orders.items.fulfillment', [$order, $item]), [
                'fulfillment_status' => 'pending',
                'reason' => 'Customer requested reactivation',
            ])
            ->assertRedirect();

        $this->assertSame('pending', $item->fresh()->fulfillment_status);
    }

    public function test_customer_and_vendor_cannot_use_admin_fulfillment_endpoint(): void
    {
        [$vendor, $order, $item, $customer] = $this->ownedItem();

        $this->actingAs($customer)
            ->patch(route('admin.orders.items.fulfillment', [$order, $item]), [
                'fulfillment_status' => 'confirmed',
            ])
            ->assertStatus(403);

        $this->actingAs($vendor)
            ->patch(route('admin.orders.items.fulfillment', [$order, $item]), [
                'fulfillment_status' => 'confirmed',
            ])
            ->assertStatus(403);

        $this->assertSame(0, FulfillmentStatusHistory::count());
    }

    public function test_admin_can_view_fulfillment_on_admin_orders(): void
    {
        [, , $item] = $this->ownedItem();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.orders'))
            ->assertOk()
            ->assertSee('Ops Widget')
            ->assertSee('Admin override')
            ->assertSee('Fulfillment:');
    }

    public function test_vendor_cannot_use_admin_only_transitions(): void
    {
        [$vendor, , $item] = $this->ownedItem();
        $item->fulfillment_status = 'processing';
        $item->save();

        $this->expectException(InvalidArgumentException::class);
        app(OrderItemFulfillmentService::class)->transition($item, 'cancelled', $vendor, 'vendor');
    }

    public function test_vendor_illegal_and_same_status_produce_no_history(): void
    {
        [$vendor, , $item] = $this->ownedItem();
        $service = app(OrderItemFulfillmentService::class);

        try {
            $service->transition($item, 'shipped', $vendor, 'vendor');
            $this->fail('Expected illegal transition.');
        } catch (InvalidArgumentException) {
            // expected
        }

        $this->assertSame(0, FulfillmentStatusHistory::where('order_item_id', $item->id)->count());

        $service->transition($item, 'pending', $vendor, 'vendor');
        $this->assertSame(0, FulfillmentStatusHistory::where('order_item_id', $item->id)->count());
        $this->assertSame('pending', $item->fresh()->fulfillment_status);
    }

    public function test_successful_vendor_transition_writes_history(): void
    {
        [$vendor, , $item] = $this->ownedItem();

        app(OrderItemFulfillmentService::class)->transition($item, 'confirmed', $vendor, 'vendor');

        $history = FulfillmentStatusHistory::where('order_item_id', $item->id)->first();
        $this->assertSame($vendor->id, $history->actor_user_id);
        $this->assertSame('vendor', $history->actor_role);
        $this->assertSame('pending', $history->from_status);
        $this->assertSame('confirmed', $history->to_status);
        $this->assertNull($history->reason);
    }

    public function test_stale_transition_cannot_bypass_state_machine(): void
    {
        [$vendor, , $item] = $this->ownedItem();
        $service = app(OrderItemFulfillmentService::class);

        $service->transition($item, 'confirmed', $vendor, 'vendor');

        $this->expectException(InvalidArgumentException::class);
        // Stale client still thinks item is pending and jumps to shipped
        $service->transition($item->fresh(), 'shipped', $vendor, 'vendor');
    }

    public function test_fulfillment_change_does_not_alter_financial_fields(): void
    {
        [$vendor, $order, $item] = $this->ownedItem();
        $price = (string) $item->price;
        $qty = (int) $item->quantity;
        $productId = (int) $item->product_id;
        $orderId = (int) $item->order_id;
        $total = (string) $order->total_price;

        app(OrderItemFulfillmentService::class)->transition($item, 'confirmed', $vendor, 'vendor');

        $fresh = $item->fresh();
        $this->assertSame($price, (string) $fresh->price);
        $this->assertSame($qty, (int) $fresh->quantity);
        $this->assertSame($productId, (int) $fresh->product_id);
        $this->assertSame($orderId, (int) $fresh->order_id);
        $this->assertSame($total, (string) $order->fresh()->total_price);
    }

    public function test_order_fulfillment_summary_for_multi_vendor_states(): void
    {
        [$vendorA, $storeA] = $this->createVendorUser(['email' => 'sum-a@example.com']);
        [, $storeB] = $this->createVendorUser(['email' => 'sum-b@example.com']);
        $productA = $this->createProductForVendor($storeA, ['name' => 'Sum A']);
        $productB = $this->createProductForVendor($storeB, ['name' => 'Sum B']);
        $customer = User::factory()->create();

        $order = Order::create([
            'order_number' => 'SN-SUM',
            'user_id' => $customer->id,
            'total_price' => 2000,
            'status' => 'paid',
        ]);
        $itemA = OrderItem::recordPurchase($order->id, $productA->id, 1, 1000);
        $itemB = OrderItem::recordPurchase($order->id, $productB->id, 1, 1000);

        $summary = app(OrderFulfillmentSummary::class);
        $this->assertSame('pending', $summary->summarize($order->fresh('items')));

        $itemA->fulfillment_status = 'shipped';
        $itemA->save();
        $itemB->fulfillment_status = 'processing';
        $itemB->save();
        $this->assertSame('partially_shipped', $summary->summarize($order->fresh('items')));

        $itemB->fulfillment_status = 'shipped';
        $itemB->save();
        $this->assertSame('shipped', $summary->summarize($order->fresh('items')));

        $itemA->fulfillment_status = 'delivered';
        $itemA->save();
        $this->assertSame('partially_delivered', $summary->summarize($order->fresh('items')));

        $itemB->fulfillment_status = 'delivered';
        $itemB->save();
        $this->assertSame('delivered', $summary->summarize($order->fresh('items')));

        $itemA->fulfillment_status = 'cancelled';
        $itemA->save();
        $itemB->fulfillment_status = 'processing';
        $itemB->save();
        $this->assertSame('mixed', $summary->summarize($order->fresh('items')));
    }

    public function test_customer_order_page_shows_progress_and_summary(): void
    {
        [$vendor, $order, $item, $customer] = $this->ownedItem();
        app(OrderItemFulfillmentService::class)->transition($item, 'confirmed', $vendor, 'vendor');

        $this->actingAs($customer)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee('Fulfillment:')
            ->assertSee('Confirmed')
            ->assertSee('✓ Confirmed')
            ->assertSee('Processing');
    }

    public function test_vendor_dashboard_shows_needs_action_queue(): void
    {
        [$vendor, $order, $item] = $this->ownedItem();

        $this->actingAs($vendor)
            ->get(route('vendor.dashboard'))
            ->assertOk()
            ->assertSee('Needs Action')
            ->assertSee('Ops Widget')
            ->assertSee($order->order_number);
    }

    public function test_vendor_orders_can_filter_by_fulfillment_status(): void
    {
        [$vendor, $order, $item] = $this->ownedItem();
        app(OrderItemFulfillmentService::class)->transition($item, 'confirmed', $vendor, 'vendor');

        $this->actingAs($vendor)
            ->get(route('vendor.orders.index', ['fulfillment' => 'confirmed']))
            ->assertOk()
            ->assertSee($order->order_number);

        $this->actingAs($vendor)
            ->get(route('vendor.orders.index', ['fulfillment' => 'shipped']))
            ->assertOk()
            ->assertDontSee($order->order_number);
    }

    public function test_customer_can_mark_own_notification_read(): void
    {
        [$vendor, , $item, $customer] = $this->ownedItem();
        app(OrderItemFulfillmentService::class)->transition($item, 'confirmed', $vendor, 'vendor');
        $notification = $customer->notifications()->first();

        $this->actingAs($customer)
            ->post(route('account.notifications.read', $notification->id))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }
}
