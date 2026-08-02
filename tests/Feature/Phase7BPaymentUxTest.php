<?php

namespace Tests\Feature;

use App\Contracts\PaymentGatewayInterface;
use App\Events\PaymentSuccessful;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentStatusHistory;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Notifications\CustomerPaymentUpdated;
use App\Services\CheckoutIdempotencyService;
use App\Services\PaymentGatewayManager;
use App\Services\PaymentService;
use App\Support\Payments\StubPaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\Support\CreatesMarketplace;
use Tests\TestCase;

class Phase7BPaymentUxTest extends TestCase
{
    use CreatesMarketplace;
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Order, 2: PaymentTransaction}
     */
    protected function unpaidOrder(string $method = 'mpesa'): array
    {
        $customer = User::factory()->create();
        [, $store] = $this->createVendorUser(['email' => 'p7b-v-'.uniqid().'@example.com']);
        $product = $this->createProductForVendor($store, ['price' => 7500]);

        $order = Order::create([
            'order_number' => 'SN-P7B-'.uniqid(),
            'user_id' => $customer->id,
            'total_price' => '7500.00',
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => $method,
            'shipping_method' => 'pickup',
        ]);
        OrderItem::recordPurchase($order->id, $product->id, 1, 7500);
        $tx = app(PaymentService::class)->ensurePendingTransaction($order, 'stub');

        return [$customer, $order->fresh(), $tx->fresh()];
    }

    public function test_stub_gateway_is_used_by_default(): void
    {
        $manager = app(PaymentGatewayManager::class);

        $this->assertSame('stub', config('payments.default'));
        $this->assertInstanceOf(StubPaymentGateway::class, $manager->default());
        $this->assertInstanceOf(StubPaymentGateway::class, app(PaymentGatewayInterface::class));
        $this->assertStringContainsString('Coming Soon', $manager->activeGatewayDisplayName());
    }

    public function test_missing_live_gateway_credentials_do_not_break_checkout(): void
    {
        config([
            'payments.gateways.mpesa.enabled' => true,
            'payments.gateways.mpesa.live_charging' => true,
        ]);

        $user = User::factory()->create();
        [, $store] = $this->createVendorUser();
        $product = $this->createProductForVendor($store, ['price' => 9000, 'stock' => 2]);
        $token = app(CheckoutIdempotencyService::class)->issue($user);

        $this->actingAs($user)
            ->withSession([
                'cart' => [
                    $product->id => [
                        'name' => $product->name,
                        'price' => 9000,
                        'quantity' => 1,
                        'image' => null,
                        'brand' => null,
                    ],
                ],
            ])
            ->post(route('checkout.place'), [
                'full_name' => 'P7B Buyer',
                'phone' => '+255700000800',
                'line1' => '1 Street',
                'city' => 'Dar es Salaam',
                'payment_method' => 'mpesa',
                'shipping_method' => 'pickup',
                'checkout_token' => $token,
            ])
            ->assertRedirect();

        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertSame('pending', $order->payment_status);
    }

    public function test_coming_soon_gateway_does_not_mark_payment_paid(): void
    {
        Event::fake([PaymentSuccessful::class]);
        [, $order, $tx] = $this->unpaidOrder('card');

        $result = app(PaymentGatewayManager::class)->initialize($order, $tx);

        $this->assertTrue($result->isComingSoon());
        $this->assertFalse($result->claimsPaymentSuccess());
        $this->assertSame('pending', $order->fresh()->payment_status);
        Event::assertNotDispatched(PaymentSuccessful::class);
    }

    public function test_customer_sees_coming_soon_state_on_order_page(): void
    {
        [$customer, $order] = $this->unpaidOrder('airtel');

        $this->actingAs($customer)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee('Payment Service Coming Soon')
            ->assertSee('No payment has been charged')
            ->assertSee('Coming Soon')
            ->assertSee('Pending')
            ->assertDontSee('Payment Successful')
            ->assertDontSee('mpesa_secret')
            ->assertDontSee('sk_live')
            ->assertDontSee('webhook_secret');
    }

    public function test_pending_payment_remains_pending_after_stub_init(): void
    {
        [, $order, $tx] = $this->unpaidOrder();
        app(PaymentGatewayManager::class)->initialize($order, $tx);

        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame('pending', $tx->fresh()->status);
        $this->assertSame(0, PaymentStatusHistory::where('to_status', 'paid')->count());
    }

    public function test_payment_successful_event_not_triggered_by_stub_initialization(): void
    {
        Event::fake([PaymentSuccessful::class]);
        Notification::fake();
        [, $order, $tx] = $this->unpaidOrder();

        app(PaymentGatewayManager::class)->initialize($order, $tx);
        app(StubPaymentGateway::class)->initializePayment($order, $tx);

        Event::assertNotDispatched(PaymentSuccessful::class);
        Notification::assertNothingSent();
    }

    public function test_admin_payment_controls_still_require_authorization(): void
    {
        [, $order] = $this->unpaidOrder();
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->patch(route('admin.orders.payment', $order), [
                'payment_status' => 'paid',
            ])
            ->assertStatus(403);

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_customer_and_vendor_cannot_mutate_payment(): void
    {
        [$customer, $order] = $this->unpaidOrder();
        [$vendor] = $this->createVendorUser(['email' => 'p7b-vendor@example.com']);

        foreach ([$customer, $vendor] as $actor) {
            $this->actingAs($actor)
                ->patch(route('admin.orders.payment', $order), [
                    'payment_status' => 'paid',
                ])
                ->assertStatus(403);
        }

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_customer_cannot_view_another_customers_payment(): void
    {
        [, $order] = $this->unpaidOrder();
        $other = User::factory()->create();

        $this->actingAs($other)
            ->get(route('account.orders.show', $order))
            ->assertStatus(403);
    }

    public function test_payment_amount_remains_server_authoritative(): void
    {
        [, $order, $tx] = $this->unpaidOrder('paypal');
        $service = app(PaymentService::class);

        app(PaymentGatewayManager::class)->initialize($order, $tx);

        $this->assertSame(
            $service->authoritativeAmount($order),
            $service->normalizeMoney($tx->fresh()->amount)
        );
    }

    public function test_checkout_idempotency_still_works(): void
    {
        $user = User::factory()->create();
        [, $store] = $this->createVendorUser();
        $product = $this->createProductForVendor($store, ['price' => 11000, 'stock' => 4]);
        $token = app(CheckoutIdempotencyService::class)->issue($user);
        $cart = [
            $product->id => [
                'name' => $product->name,
                'price' => 11000,
                'quantity' => 1,
                'image' => null,
                'brand' => null,
            ],
        ];
        $payload = [
            'full_name' => 'P7B Idem',
            'phone' => '+255700000801',
            'line1' => '1 Street',
            'city' => 'Dar es Salaam',
            'payment_method' => 'mpesa',
            'shipping_method' => 'pickup',
            'checkout_token' => $token,
        ];

        $this->actingAs($user)->withSession(['cart' => $cart])->post(route('checkout.place'), $payload);
        $this->actingAs($user)->withSession(['cart' => $cart])->post(route('checkout.place'), $payload);

        $this->assertSame(1, Order::count());
        $this->assertSame(1, PaymentTransaction::count());
        $this->assertSame('pending', Order::first()->payment_status);
    }

    public function test_payment_state_machine_still_works_and_notifies_once(): void
    {
        Notification::fake();
        [$customer, $order] = $this->unpaidOrder();
        $admin = User::factory()->admin()->create();
        $service = app(PaymentService::class);

        $service->transitionOrderPayment($order, 'processing', $admin);
        $service->transitionOrderPayment($order->fresh(), 'paid', $admin, null, 'manual', 'P7B-REF');
        $service->transitionOrderPayment($order->fresh(), 'paid', $admin, null, 'manual', 'P7B-REF');

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(1, PaymentStatusHistory::where('to_status', 'paid')->count());
        Notification::assertSentToTimes($customer, CustomerPaymentUpdated::class, 1);
    }

    public function test_admin_orders_page_separates_order_payment_fulfillment(): void
    {
        [, $order] = $this->unpaidOrder();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.orders'))
            ->assertOk()
            ->assertSee('Order status')
            ->assertSee('Payment status')
            ->assertSee('Fulfillment')
            ->assertSee('Stub / Offline / Coming Soon')
            ->assertSee($order->order_number)
            ->assertDontSee('sk_live')
            ->assertDontSee('webhook_secret');
    }

    public function test_resolve_or_stub_fails_closed_for_unknown_gateway(): void
    {
        $gateway = app(PaymentGatewayManager::class)->resolveOrStub('totally-missing-gateway');
        $this->assertInstanceOf(StubPaymentGateway::class, $gateway);
        $this->assertFalse($gateway->supportsLiveCharging());
    }

    public function test_notification_copy_is_customer_friendly_for_paid(): void
    {
        [, $order, $tx] = $this->unpaidOrder();
        $admin = User::factory()->admin()->create();
        app(PaymentService::class)->transitionOrderPayment($order, 'processing', $admin);
        $paid = app(PaymentService::class)->transitionOrderPayment($order->fresh(), 'paid', $admin);

        $notification = new CustomerPaymentUpdated($paid, 'successful');
        $payload = $notification->toArray($order->user);

        $this->assertStringContainsString('Payment received successfully', $payload['body']);
        $this->assertStringContainsString($order->order_number, $payload['body']);
        $this->assertArrayNotHasKey('api_key', $payload);
        $this->assertArrayNotHasKey('secret', $payload);
    }
}
