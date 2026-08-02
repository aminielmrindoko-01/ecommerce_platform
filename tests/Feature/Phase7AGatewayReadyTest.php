<?php

namespace Tests\Feature;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentStatusHistory;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\CheckoutIdempotencyService;
use App\Services\PaymentGatewayManager;
use App\Services\PaymentService;
use App\Support\Payments\GatewayInitializationResult;
use App\Support\Payments\StubPaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Support\CreatesMarketplace;
use Tests\TestCase;

class Phase7AGatewayReadyTest extends TestCase
{
    use CreatesMarketplace;
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Order, 2: PaymentTransaction}
     */
    protected function unpaidOrder(string $method = 'mpesa'): array
    {
        $customer = User::factory()->create();
        [, $store] = $this->createVendorUser(['email' => 'p7a-v-'.uniqid().'@example.com']);
        $product = $this->createProductForVendor($store, ['price' => 5000]);

        $order = Order::create([
            'order_number' => 'SN-P7A-'.uniqid(),
            'user_id' => $customer->id,
            'total_price' => '5000.00',
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => $method,
            'shipping_method' => 'pickup',
        ]);
        OrderItem::recordPurchase($order->id, $product->id, 1, 5000);

        $tx = app(PaymentService::class)->ensurePendingTransaction($order, 'stub');

        return [$customer, $order->fresh(), $tx->fresh()];
    }

    public function test_unavailable_payment_method_shows_coming_soon_state(): void
    {
        [$customer, $order, $tx] = $this->unpaidOrder('mpesa');
        $init = app(PaymentGatewayManager::class)->initialize($order, $tx);

        $this->assertTrue($init->isComingSoon());
        $this->assertSame(GatewayInitializationResult::STATUS_COMING_SOON, $init->status);
        $this->assertSame('M-Pesa', $init->methodLabel);
        $this->assertStringContainsString('coming soon', strtolower($init->headline.' '.$init->message));

        $this->actingAs($customer)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee('Payment Service Coming Soon')
            ->assertSee('M-Pesa')
            ->assertSee('Coming Soon')
            ->assertDontSee('Payment successful')
            ->assertDontSee('Transaction successful');
    }

    public function test_stub_gateway_never_automatically_marks_payment_paid(): void
    {
        [, $order, $tx] = $this->unpaidOrder('airtel');
        $gateway = app(StubPaymentGateway::class);

        $this->assertFalse($gateway->supportsLiveCharging());
        $init = $gateway->initializePayment($order, $tx);

        $this->assertFalse($init->claimsPaymentSuccess());
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame('pending', $tx->fresh()->status);
        $this->assertNull($tx->fresh()->paid_at);
    }

    public function test_customer_cannot_fake_payment_success_via_checkout_or_order_page(): void
    {
        $user = User::factory()->create();
        [, $store] = $this->createVendorUser();
        $product = $this->createProductForVendor($store, ['price' => 10000, 'stock' => 3]);
        $token = app(CheckoutIdempotencyService::class)->issue($user);

        $this->actingAs($user)
            ->withSession([
                'cart' => [
                    $product->id => [
                        'name' => $product->name,
                        'price' => 10000,
                        'quantity' => 1,
                        'image' => null,
                        'brand' => null,
                    ],
                ],
            ])
            ->post(route('checkout.place'), [
                'full_name' => 'P7A Buyer',
                'phone' => '+255700000700',
                'line1' => '1 Street',
                'city' => 'Dar es Salaam',
                'payment_method' => 'mpesa',
                'shipping_method' => 'pickup',
                'checkout_token' => $token,
                'payment_status' => 'paid',
                'amount' => '1.00',
            ])
            ->assertRedirect();

        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('mpesa', $order->payment_method);
        $this->assertSame(0, bccomp(
            app(PaymentService::class)->normalizeMoney($order->total_price),
            app(PaymentService::class)->normalizeMoney($order->latestPaymentTransaction->amount),
            2
        ));

        $this->actingAs($user)
            ->get(route('checkout.confirmation', $order))
            ->assertOk()
            ->assertSee('Payment Service Coming Soon')
            ->assertSee('Payment status')
            ->assertDontSee('Payment Successful');

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_payment_remains_pending_without_gateway_verification(): void
    {
        [, $order, $tx] = $this->unpaidOrder('card');
        $manager = app(PaymentGatewayManager::class);

        $manager->initialize($order, $tx);
        $verify = $manager->resolveForMethod('card')->verifyPayment($tx, [
            'status' => 'success',
            'paid' => true,
            'amount' => '5000.00',
        ]);

        $this->assertFalse($verify->successful);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame(0, PaymentStatusHistory::where('to_status', 'paid')->count());
    }

    public function test_disabled_gateway_cannot_initialize_a_real_charge(): void
    {
        $manager = app(PaymentGatewayManager::class);

        $this->assertFalse($manager->isLiveChargingConfigured('mpesa'));
        $this->assertFalse($manager->isLiveChargingConfigured('stripe'));
        $this->assertFalse($manager->isLiveChargingConfigured('paypal'));

        $gateway = $manager->resolve('mpesa');
        $this->assertInstanceOf(StubPaymentGateway::class, $gateway);
        $this->assertFalse($gateway->supportsLiveCharging());
    }

    public function test_gateway_resolver_selects_configured_gateway_correctly(): void
    {
        $manager = app(PaymentGatewayManager::class);

        $this->assertInstanceOf(StubPaymentGateway::class, $manager->default());
        $this->assertInstanceOf(StubPaymentGateway::class, $manager->resolveForMethod('mpesa'));
        $this->assertInstanceOf(StubPaymentGateway::class, $manager->resolve('stub'));

        $methods = $manager->checkoutMethods();
        $this->assertArrayHasKey('mpesa', $methods);
        $this->assertTrue($methods['mpesa']['coming_soon']);
        $this->assertSame('Coming soon', $methods['mpesa']['badge']);
        $this->assertTrue($methods['cod']['offline']);
        $this->assertFalse($methods['cod']['coming_soon']);
    }

    public function test_unknown_gateway_is_rejected_safely(): void
    {
        $manager = app(PaymentGatewayManager::class);

        $this->expectException(InvalidArgumentException::class);
        $manager->resolve('not-a-real-gateway');
    }

    public function test_unknown_payment_method_is_rejected_safely(): void
    {
        $manager = app(PaymentGatewayManager::class);

        $this->expectException(InvalidArgumentException::class);
        $manager->resolveForMethod('bitcoin');
    }

    public function test_customer_cannot_access_another_customers_payment(): void
    {
        [, $order] = $this->unpaidOrder();
        $other = User::factory()->create();

        $this->actingAs($other)
            ->get(route('account.orders.show', $order))
            ->assertStatus(403);
    }

    public function test_vendor_cannot_mutate_payment(): void
    {
        [, $order] = $this->unpaidOrder();
        [$vendor] = $this->createVendorUser(['email' => 'p7a-vendor@example.com']);

        $this->actingAs($vendor)
            ->patch(route('admin.orders.payment', $order), [
                'payment_status' => 'paid',
            ])
            ->assertStatus(403);

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_admin_payment_controls_still_work(): void
    {
        [, $order] = $this->unpaidOrder();
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
                'provider_reference' => 'P7A-ADMIN-1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_checkout_idempotency_still_works_with_gateway_init(): void
    {
        $user = User::factory()->create();
        [, $store] = $this->createVendorUser();
        $product = $this->createProductForVendor($store, ['price' => 12000, 'stock' => 5]);
        $token = app(CheckoutIdempotencyService::class)->issue($user);
        $cart = [
            $product->id => [
                'name' => $product->name,
                'price' => 12000,
                'quantity' => 1,
                'image' => null,
                'brand' => null,
            ],
        ];
        $payload = [
            'full_name' => 'Idem P7A',
            'phone' => '+255700000701',
            'line1' => '1 Street',
            'city' => 'Dar es Salaam',
            'payment_method' => 'airtel',
            'shipping_method' => 'pickup',
            'checkout_token' => $token,
        ];

        $this->actingAs($user)->withSession(['cart' => $cart])->post(route('checkout.place'), $payload);
        $this->actingAs($user)->withSession(['cart' => $cart])->post(route('checkout.place'), $payload);

        $this->assertSame(1, Order::count());
        $this->assertSame('pending', Order::first()->payment_status);
        $this->assertSame(1, PaymentTransaction::count());
    }

    public function test_amount_remains_authoritative_from_order_total(): void
    {
        [, $order, $tx] = $this->unpaidOrder('paypal');
        $service = app(PaymentService::class);

        $this->assertSame(
            $service->authoritativeAmount($order),
            $service->normalizeMoney($tx->amount)
        );

        app(PaymentGatewayManager::class)->initialize($order, $tx);

        $this->assertSame(
            $service->authoritativeAmount($order),
            $service->normalizeMoney($tx->fresh()->amount)
        );
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_container_default_gateway_binding_is_stub(): void
    {
        $gateway = app(PaymentGatewayInterface::class);
        $this->assertInstanceOf(StubPaymentGateway::class, $gateway);
        $this->assertFalse($gateway->supportsLiveCharging());
    }

    public function test_checkout_page_shows_coming_soon_badges(): void
    {
        $user = User::factory()->create();
        [, $store] = $this->createVendorUser();
        $product = $this->createProductForVendor($store, ['price' => 8000, 'stock' => 2]);

        $this->actingAs($user)
            ->withSession([
                'cart' => [
                    $product->id => [
                        'name' => $product->name,
                        'price' => 8000,
                        'quantity' => 1,
                        'image' => null,
                        'brand' => null,
                    ],
                ],
            ])
            ->get(route('checkout'))
            ->assertOk()
            ->assertSee('Coming Soon')
            ->assertSee('M-Pesa')
            ->assertSee('Online payment is currently unavailable')
            ->assertDontSee('Payment Successful');
    }
}
