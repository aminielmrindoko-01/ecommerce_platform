<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentStatusHistory;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Notifications\CustomerPaymentUpdated;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Tests\Support\CreatesMarketplace;
use Tests\TestCase;

class PaymentOperationsTest extends TestCase
{
    use CreatesMarketplace;
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Order, 2: PaymentTransaction}
     */
    protected function unpaidOrder(?User $customer = null): array
    {
        $customer ??= User::factory()->create();
        [, $store] = $this->createVendorUser(['email' => 'pay-v-'.uniqid().'@example.com']);
        $product = $this->createProductForVendor($store, ['price' => 5000]);

        $order = Order::create([
            'order_number' => 'SN-PAY-'.uniqid(),
            'user_id' => $customer->id,
            'total_price' => 5000,
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'mpesa',
            'shipping_method' => 'pickup',
        ]);
        OrderItem::recordPurchase($order->id, $product->id, 1, 5000);

        $tx = app(PaymentService::class)->ensurePendingTransaction($order, 'stub');

        return [$customer, $order->fresh(), $tx->fresh()];
    }

    public function test_customer_can_view_own_payment_info(): void
    {
        [$customer, $order, $tx] = $this->unpaidOrder();

        $this->actingAs($customer)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee('Payment status')
            ->assertSee('Pending')
            ->assertSee($tx->reference);
    }

    public function test_customer_cannot_view_another_customers_payment(): void
    {
        [, $order] = $this->unpaidOrder();
        $other = User::factory()->create();

        $this->actingAs($other)
            ->get(route('account.orders.show', $order))
            ->assertStatus(403);
    }

    public function test_customer_and_vendor_cannot_mutate_payment(): void
    {
        [$customer, $order] = $this->unpaidOrder();
        [$vendor] = $this->createVendorUser(['email' => 'pay-vendor@example.com']);

        $this->actingAs($customer)
            ->patch(route('admin.orders.payment', $order), [
                'payment_status' => 'paid',
            ])
            ->assertStatus(403);

        $this->actingAs($vendor)
            ->patch(route('admin.orders.payment', $order), [
                'payment_status' => 'paid',
            ])
            ->assertStatus(403);

        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame(0, PaymentStatusHistory::count());
    }

    public function test_guest_cannot_access_payment_admin(): void
    {
        [, $order] = $this->unpaidOrder();

        $this->patch(route('admin.orders.payment', $order), [
            'payment_status' => 'paid',
        ])->assertRedirect(route('login'));
    }

    public function test_admin_can_mark_paid_with_audit_and_notification(): void
    {
        Notification::fake();
        [$customer, $order, $tx] = $this->unpaidOrder();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('admin.orders.payment', $order), [
                'payment_status' => 'processing',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($admin)
            ->patch(route('admin.orders.payment', $order), [
                'payment_status' => 'paid',
                'provider_reference' => 'MANUAL-REF-1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $order->refresh();
        $tx->refresh();

        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('paid', $tx->status);
        $this->assertSame('MANUAL-REF-1', $tx->provider_reference);
        $this->assertNotNull($tx->paid_at);
        $this->assertSame('paid', $order->status);

        $history = PaymentStatusHistory::where('payment_transaction_id', $tx->id)
            ->where('to_status', 'paid')
            ->first();
        $this->assertNotNull($history);
        $this->assertSame($admin->id, $history->actor_user_id);

        Notification::assertSentTo($customer, CustomerPaymentUpdated::class);
    }

    public function test_admin_failed_and_cancelled_require_reason(): void
    {
        [, $order] = $this->unpaidOrder();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.orders'))
            ->patch(route('admin.orders.payment', $order), [
                'payment_status' => 'failed',
            ])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)
            ->patch(route('admin.orders.payment', $order), [
                'payment_status' => 'failed',
                'reason' => 'Customer abandoned payment',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('failed', $order->fresh()->payment_status);
    }

    public function test_illegal_payment_transitions_are_rejected(): void
    {
        [, $order] = $this->unpaidOrder();
        $admin = User::factory()->admin()->create();
        $service = app(PaymentService::class);

        $service->transitionOrderPayment($order, 'processing', $admin);
        $service->transitionOrderPayment($order->fresh(), 'paid', $admin);

        $this->expectException(InvalidArgumentException::class);
        $service->transitionOrderPayment($order->fresh(), 'pending', $admin);
    }

    public function test_duplicate_provider_reference_is_idempotent_for_same_order(): void
    {
        [, $order] = $this->unpaidOrder();
        $admin = User::factory()->admin()->create();
        $service = app(PaymentService::class);

        $service->transitionOrderPayment($order, 'processing', $admin);
        $first = $service->transitionOrderPayment(
            $order->fresh(),
            'paid',
            $admin,
            null,
            'manual',
            'DUP-REF-100'
        );

        $second = $service->transitionOrderPayment(
            $order->fresh(),
            'paid',
            $admin,
            null,
            'manual',
            'DUP-REF-100'
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PaymentTransaction::where('status', 'paid')->count());
        $this->assertSame(1, PaymentStatusHistory::where('to_status', 'paid')->count());
    }

    public function test_provider_reference_cannot_be_reused_on_another_order(): void
    {
        [, $orderA] = $this->unpaidOrder();
        [, $orderB] = $this->unpaidOrder();
        $admin = User::factory()->admin()->create();
        $service = app(PaymentService::class);

        $service->transitionOrderPayment($orderA, 'processing', $admin);
        $service->transitionOrderPayment($orderA->fresh(), 'paid', $admin, null, 'manual', 'SHARED-REF');

        $service->transitionOrderPayment($orderB, 'processing', $admin);

        $this->expectException(InvalidArgumentException::class);
        $service->transitionOrderPayment($orderB->fresh(), 'paid', $admin, null, 'manual', 'SHARED-REF');
    }

    public function test_already_paid_order_cannot_be_paid_again_via_new_transition(): void
    {
        [, $order] = $this->unpaidOrder();
        $admin = User::factory()->admin()->create();
        $service = app(PaymentService::class);

        $service->transitionOrderPayment($order, 'processing', $admin);
        $service->transitionOrderPayment($order->fresh(), 'paid', $admin);

        $before = PaymentStatusHistory::count();
        $service->transitionOrderPayment($order->fresh(), 'paid', $admin);
        $this->assertSame($before, PaymentStatusHistory::count());
    }

    public function test_amount_mismatch_is_rejected(): void
    {
        [, $order, $tx] = $this->unpaidOrder();
        $admin = User::factory()->admin()->create();

        $tx->amount = '1.00';
        $tx->save();

        $this->expectException(InvalidArgumentException::class);
        app(PaymentService::class)->transitionOrderPayment($order, 'processing', $admin);
    }

    public function test_currency_mismatch_is_rejected(): void
    {
        [, $order, $tx] = $this->unpaidOrder();
        $admin = User::factory()->admin()->create();

        $tx->currency = 'USD';
        $tx->save();

        $this->expectException(InvalidArgumentException::class);
        app(PaymentService::class)->transitionOrderPayment($order, 'processing', $admin);
    }

    public function test_payment_does_not_alter_line_item_financial_fields(): void
    {
        [, $order, $tx] = $this->unpaidOrder();
        $admin = User::factory()->admin()->create();
        $item = $order->items()->first();
        $price = (string) $item->price;
        $qty = (int) $item->quantity;
        $productId = (int) $item->product_id;
        $total = (string) $order->total_price;

        app(PaymentService::class)->transitionOrderPayment($order, 'processing', $admin);
        app(PaymentService::class)->transitionOrderPayment($order->fresh(), 'paid', $admin);

        $item->refresh();
        $this->assertSame($price, (string) $item->price);
        $this->assertSame($qty, (int) $item->quantity);
        $this->assertSame($productId, (int) $item->product_id);
        $this->assertSame($total, (string) $order->fresh()->total_price);
        $this->assertSame($total, number_format((float) $tx->fresh()->amount, 2, '.', ''));
    }

    public function test_admin_orders_page_shows_payment_fields(): void
    {
        [, $order, $tx] = $this->unpaidOrder();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.orders'))
            ->assertOk()
            ->assertSee('Payment status')
            ->assertSee($tx->reference)
            ->assertSee('Update payment');
    }

    public function test_checkout_idempotency_token_blocks_reuse(): void
    {
        $user = User::factory()->create();
        [, $store] = $this->createVendorUser();
        $product = $this->createProductForVendor($store, ['price' => 10000, 'stock' => 5]);
        $token = app(\App\Services\CheckoutIdempotencyService::class)->issue($user);
        $cart = [
            $product->id => [
                'name' => $product->name,
                'price' => 10000,
                'quantity' => 1,
                'image' => null,
                'brand' => null,
            ],
        ];
        $payload = [
            'full_name' => 'Idem Buyer',
            'phone' => '+255700000200',
            'line1' => '1 Street',
            'city' => 'Dar es Salaam',
            'payment_method' => 'cod',
            'shipping_method' => 'pickup',
            'checkout_token' => $token,
        ];

        $this->actingAs($user)
            ->withSession(['cart' => $cart])
            ->post(route('checkout.place'), $payload)
            ->assertRedirect();

        $this->assertSame(1, Order::count());

        $this->actingAs($user)
            ->withSession(['cart' => $cart])
            ->post(route('checkout.place'), $payload)
            ->assertRedirect(route('account.orders'));

        $this->assertSame(1, Order::count());
    }

    public function test_same_status_payment_transition_creates_no_history(): void
    {
        [, $order] = $this->unpaidOrder();
        $admin = User::factory()->admin()->create();
        $service = app(PaymentService::class);

        $before = PaymentStatusHistory::count();
        $service->transitionOrderPayment($order, 'pending', $admin);
        $this->assertSame($before, PaymentStatusHistory::count());
    }

    public function test_cancelled_to_paid_is_rejected(): void
    {
        [, $order] = $this->unpaidOrder();
        $admin = User::factory()->admin()->create();
        $service = app(PaymentService::class);

        $service->transitionOrderPayment($order, 'cancelled', $admin, 'Customer cancelled');

        $this->expectException(InvalidArgumentException::class);
        $service->transitionOrderPayment($order->fresh(), 'paid', $admin);
    }
}
