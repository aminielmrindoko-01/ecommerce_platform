<?php

namespace Tests\Feature;

use App\Events\PaymentSuccessful;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentStatusHistory;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\PaymentGatewayManager;
use App\Services\PaymentService;
use App\Services\PesapalPaymentProcessor;
use App\Support\Payments\GatewayInitializationResult;
use App\Support\Payments\PesapalClient;
use App\Support\Payments\PesapalGateway;
use App\Support\Payments\StubPaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Support\CreatesMarketplace;
use Tests\TestCase;

class PesapalGatewayTest extends TestCase
{
    use CreatesMarketplace;
    use RefreshDatabase;

    protected function enablePesapalSandbox(): void
    {
        config([
            'payments.default' => 'pesapal',
            'payments.gateways.pesapal.enabled' => true,
            'payments.gateways.pesapal.live_charging' => true,
            'payments.gateways.pesapal.environment' => 'sandbox',
            'payments.gateways.pesapal.consumer_key' => 'test-key',
            'payments.gateways.pesapal.consumer_secret' => 'test-secret',
            'payments.gateways.pesapal.callback_url' => 'http://localhost/payments/pesapal/callback',
            'payments.gateways.pesapal.ipn_url' => 'http://localhost/api/payments/pesapal/ipn',
            'payments.gateways.pesapal.timeout' => 5,
        ]);
        Cache::flush();
    }

    /**
     * @return array{0: User, 1: Order, 2: PaymentTransaction}
     */
    protected function unpaidOrder(string $method = 'pesapal', string $total = '5000.00'): array
    {
        $customer = User::factory()->create(['email' => 'p8a-'.uniqid().'@example.com']);
        [, $store] = $this->createVendorUser(['email' => 'p8a-v-'.uniqid().'@example.com']);
        $product = $this->createProductForVendor($store, ['price' => 5000]);

        $order = Order::create([
            'order_number' => 'SN-P8A-'.uniqid(),
            'user_id' => $customer->id,
            'total_price' => $total,
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => $method,
            'shipping_method' => 'pickup',
            'shipping_address' => [
                'full_name' => 'Pesa Buyer',
                'phone' => '+255700000900',
                'line1' => '1 Street',
                'city' => 'Dar es Salaam',
            ],
        ]);
        OrderItem::recordPurchase($order->id, $product->id, 1, 5000);

        $tx = app(PaymentService::class)->ensurePendingTransaction($order, 'pesapal');

        return [$customer, $order->fresh(), $tx->fresh()];
    }

    protected function fakePesapalHappyPath(string $trackingId = 'track-abc-123'): void
    {
        Http::fake([
            '*/api/Auth/RequestToken' => Http::response([
                'token' => 'sandbox-token',
                'expiryDate' => now()->addMinutes(5)->toIso8601String(),
                'status' => '200',
                'message' => 'Success',
            ], 200),
            '*/api/URLSetup/RegisterIPN' => Http::response([
                'ipn_id' => 'ipn-guid-001',
                'status' => '200',
            ], 200),
            '*/api/Transactions/SubmitOrderRequest' => Http::response([
                'order_tracking_id' => $trackingId,
                'merchant_reference' => 'ignored',
                'redirect_url' => 'https://cybqa.pesapal.com/pesapaliframe/PesapalIframe3/Index/?OrderTrackingId='.$trackingId,
                'status' => '200',
            ], 200),
            '*/api/Transactions/GetTransactionStatus*' => Http::response([
                'payment_method' => 'MPESA',
                'amount' => 5000,
                'currency' => 'TZS',
                'status_code' => 1,
                'payment_status_description' => 'Completed',
                'merchant_reference' => null,
                'status' => '200',
            ], 200),
        ]);
    }

    public function test_stub_remains_default(): void
    {
        $this->assertSame('stub', config('payments.default'));
        $this->assertInstanceOf(StubPaymentGateway::class, app(PaymentGatewayManager::class)->default());
    }

    public function test_sandbox_configuration_loads_correctly(): void
    {
        $this->enablePesapalSandbox();
        $cfg = config('payments.gateways.pesapal');

        $this->assertSame('sandbox', $cfg['environment']);
        $this->assertSame('https://cybqa.pesapal.com/pesapalv3', $cfg['base_urls']['sandbox']);
        $this->assertTrue(app(PaymentGatewayManager::class)->gatewayAllowsLiveCharging('pesapal'));
        $this->assertInstanceOf(PesapalGateway::class, app(PaymentGatewayManager::class)->resolve('pesapal'));
    }

    public function test_missing_credentials_do_not_crash_application(): void
    {
        config([
            'payments.default' => 'pesapal',
            'payments.gateways.pesapal.enabled' => true,
            'payments.gateways.pesapal.live_charging' => true,
            'payments.gateways.pesapal.environment' => 'sandbox',
            'payments.gateways.pesapal.consumer_key' => '',
            'payments.gateways.pesapal.consumer_secret' => '',
        ]);

        [, $order, $tx] = $this->unpaidOrder();
        $result = app(PaymentGatewayManager::class)->initialize($order, $tx);

        $this->assertTrue($result->isComingSoon() || $result->status === GatewayInitializationResult::STATUS_COMING_SOON
            || $result->provider === 'stub' || $result->status === GatewayInitializationResult::STATUS_COMING_SOON);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertFalse($result->claimsPaymentSuccess());
    }

    public function test_production_environment_is_rejected_for_phase_8a(): void
    {
        $this->enablePesapalSandbox();
        config(['payments.gateways.pesapal.environment' => 'production']);

        $this->assertFalse(app(PaymentGatewayManager::class)->gatewayAllowsLiveCharging('pesapal'));

        [, $order, $tx] = $this->unpaidOrder();
        // Force gateway instance to exercise production guard.
        $result = app(PesapalGateway::class)->initializePayment($order, $tx);
        $this->assertTrue($result->isComingSoon());
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_successful_sandbox_authentication(): void
    {
        $this->enablePesapalSandbox();
        Http::fake([
            '*/api/Auth/RequestToken' => Http::response([
                'token' => 'tok-1',
                'expiryDate' => now()->addMinutes(5)->toIso8601String(),
                'status' => '200',
            ], 200),
        ]);

        $token = app(PesapalClient::class)->requestToken();
        $this->assertSame('tok-1', $token['token']);
    }

    public function test_failed_authentication(): void
    {
        $this->enablePesapalSandbox();
        Http::fake([
            '*/api/Auth/RequestToken' => Http::response(['error' => ['message' => 'denied']], 401),
        ]);

        $this->expectException(RuntimeException::class);
        app(PesapalClient::class)->requestToken();
    }

    public function test_malformed_auth_response_and_missing_token(): void
    {
        $this->enablePesapalSandbox();
        Http::fake([
            '*/api/Auth/RequestToken' => Http::response(['status' => '200'], 200),
        ]);

        $this->expectException(RuntimeException::class);
        app(PesapalClient::class)->requestToken();
    }

    public function test_valid_payment_initialization_returns_redirect_url(): void
    {
        $this->enablePesapalSandbox();
        $this->fakePesapalHappyPath('track-init-1');
        [, $order, $tx] = $this->unpaidOrder();

        $result = app(PesapalGateway::class)->initializePayment($order, $tx);

        $this->assertSame(GatewayInitializationResult::STATUS_REQUIRES_ACTION, $result->status);
        $this->assertFalse($result->claimsPaymentSuccess());
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertStringContainsString('OrderTrackingId=track-init-1', $result->metadata['redirect_url']);
        $this->assertSame('5000.00', $result->metadata['amount']);
    }

    public function test_initialization_uses_authoritative_order_total(): void
    {
        $this->enablePesapalSandbox();
        $this->fakePesapalHappyPath();
        [, $order, $tx] = $this->unpaidOrder('pesapal', '1000.50');

        $result = app(PesapalGateway::class)->initializePayment($order, $tx);
        $this->assertSame('1000.50', $result->metadata['amount']);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'SubmitOrderRequest')) {
                return false;
            }
            $data = $request->data();

            return isset($data['amount']) && abs((float) $data['amount'] - 1000.50) < 0.001
                && ($data['currency'] ?? null) === 'TZS'
                && ($data['id'] ?? null) !== null;
        });
    }

    public function test_customer_cannot_initialize_another_customers_order_confirmation(): void
    {
        [, $order] = $this->unpaidOrder();
        $other = User::factory()->create();

        $this->actingAs($other)
            ->get(route('checkout.confirmation', $order))
            ->assertStatus(403);
    }

    public function test_vendor_cannot_mutate_payment(): void
    {
        [, $order] = $this->unpaidOrder();
        [$vendor] = $this->createVendorUser(['email' => 'p8a-vendor@example.com']);

        $this->actingAs($vendor)
            ->patch(route('admin.orders.payment', $order), ['payment_status' => 'paid'])
            ->assertStatus(403);
    }

    public function test_ipn_verified_successful_payment_goes_through_payment_service(): void
    {
        Event::fake([PaymentSuccessful::class]);
        $this->enablePesapalSandbox();
        [, $order, $tx] = $this->unpaidOrder();

        Http::fake([
            '*/api/Auth/RequestToken' => Http::response(['token' => 'tok', 'status' => '200'], 200),
            '*/api/Transactions/GetTransactionStatus*' => Http::response([
                'amount' => 5000,
                'currency' => 'TZS',
                'status_code' => 1,
                'payment_status_description' => 'Completed',
                'merchant_reference' => $tx->reference,
                'status' => '200',
            ], 200),
        ]);

        $this->postJson(route('payments.pesapal.ipn'), [
            'OrderTrackingId' => 'track-paid-1',
            'OrderMerchantReference' => $tx->reference,
            'OrderNotificationType' => 'IPNCHANGE',
        ])->assertOk()->assertJson(['status' => 200]);

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('track-paid-1', $tx->fresh()->provider_reference);
        $this->assertSame(1, PaymentStatusHistory::where('to_status', 'paid')->count());
        Event::assertDispatched(PaymentSuccessful::class);
    }

    public function test_browser_return_alone_does_not_mark_paid_without_verification(): void
    {
        $this->enablePesapalSandbox();
        [$customer, $order, $tx] = $this->unpaidOrder();

        // Status endpoint reports not completed.
        Http::fake([
            '*/api/Auth/RequestToken' => Http::response(['token' => 'tok', 'status' => '200'], 200),
            '*/api/Transactions/GetTransactionStatus*' => Http::response([
                'amount' => 5000,
                'currency' => 'TZS',
                'status_code' => 0,
                'payment_status_description' => 'INVALID',
                'merchant_reference' => $tx->reference,
                'status' => '200',
            ], 200),
        ]);

        $this->actingAs($customer)
            ->get(route('payments.pesapal.callback', [
                'OrderTrackingId' => 'track-pending-1',
                'OrderMerchantReference' => $tx->reference,
                'OrderNotificationType' => 'CALLBACKURL',
            ]))
            ->assertRedirect(route('checkout.confirmation', $order));

        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame(0, PaymentStatusHistory::where('to_status', 'paid')->count());
    }

    public function test_amount_mismatch_rejects_paid_transition(): void
    {
        $this->enablePesapalSandbox();
        [, $order, $tx] = $this->unpaidOrder();

        Http::fake([
            '*/api/Auth/RequestToken' => Http::response(['token' => 'tok', 'status' => '200'], 200),
            '*/api/Transactions/GetTransactionStatus*' => Http::response([
                'amount' => 1,
                'currency' => 'TZS',
                'status_code' => 1,
                'payment_status_description' => 'Completed',
                'merchant_reference' => $tx->reference,
                'status' => '200',
            ], 200),
        ]);

        $result = app(PesapalPaymentProcessor::class)->processNotification([
            'OrderTrackingId' => 'track-mismatch',
            'OrderMerchantReference' => $tx->reference,
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_currency_mismatch_rejects_paid_transition(): void
    {
        $this->enablePesapalSandbox();
        [, $order, $tx] = $this->unpaidOrder();

        Http::fake([
            '*/api/Auth/RequestToken' => Http::response(['token' => 'tok', 'status' => '200'], 200),
            '*/api/Transactions/GetTransactionStatus*' => Http::response([
                'amount' => 5000,
                'currency' => 'KES',
                'status_code' => 1,
                'payment_status_description' => 'Completed',
                'merchant_reference' => $tx->reference,
                'status' => '200',
            ], 200),
        ]);

        $result = app(PesapalPaymentProcessor::class)->processNotification([
            'OrderTrackingId' => 'track-currency',
            'OrderMerchantReference' => $tx->reference,
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_duplicate_ipn_is_idempotent(): void
    {
        Event::fake([PaymentSuccessful::class]);
        $this->enablePesapalSandbox();
        [, $order, $tx] = $this->unpaidOrder();

        Http::fake([
            '*/api/Auth/RequestToken' => Http::response(['token' => 'tok', 'status' => '200'], 200),
            '*/api/Transactions/GetTransactionStatus*' => Http::response([
                'amount' => 5000,
                'currency' => 'TZS',
                'status_code' => 1,
                'payment_status_description' => 'Completed',
                'merchant_reference' => $tx->reference,
                'status' => '200',
            ], 200),
        ]);

        $payload = [
            'OrderTrackingId' => 'track-dup-1',
            'OrderMerchantReference' => $tx->reference,
            'OrderNotificationType' => 'IPNCHANGE',
        ];

        $this->postJson(route('payments.pesapal.ipn'), $payload)->assertOk();
        $this->postJson(route('payments.pesapal.ipn'), $payload)->assertOk();

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(1, PaymentStatusHistory::where('to_status', 'paid')->count());
        Event::assertDispatchedTimes(PaymentSuccessful::class, 1);
    }

    public function test_provider_reference_cannot_be_reused_on_another_order(): void
    {
        $this->enablePesapalSandbox();
        [, $orderA, $txA] = $this->unpaidOrder();
        [, $orderB, $txB] = $this->unpaidOrder();

        Http::fake([
            '*/api/Auth/RequestToken' => Http::response(['token' => 'tok', 'status' => '200'], 200),
            '*/api/Transactions/GetTransactionStatus*' => Http::response([
                'amount' => 5000,
                'currency' => 'TZS',
                'status_code' => 1,
                'payment_status_description' => 'Completed',
                'status' => '200',
            ], 200),
        ]);

        app(PesapalPaymentProcessor::class)->processNotification([
            'OrderTrackingId' => 'shared-track',
            'OrderMerchantReference' => $txA->reference,
        ]);
        $this->assertSame('paid', $orderA->fresh()->payment_status);

        app(PesapalPaymentProcessor::class)->processNotification([
            'OrderTrackingId' => 'shared-track',
            'OrderMerchantReference' => $txB->reference,
        ]);

        $this->assertNotSame('paid', $orderB->fresh()->payment_status);
    }

    public function test_unknown_provider_transaction_is_rejected(): void
    {
        $this->enablePesapalSandbox();

        $this->postJson(route('payments.pesapal.ipn'), [
            'OrderTrackingId' => 'x',
            'OrderMerchantReference' => 'PAY-DOESNOTEXIST',
            'OrderNotificationType' => 'IPNCHANGE',
        ])->assertOk()->assertJson(['status' => 500]);
    }

    public function test_failed_pesapal_status_marks_failed_via_payment_service(): void
    {
        $this->enablePesapalSandbox();
        [, $order, $tx] = $this->unpaidOrder();

        Http::fake([
            '*/api/Auth/RequestToken' => Http::response(['token' => 'tok', 'status' => '200'], 200),
            '*/api/Transactions/GetTransactionStatus*' => Http::response([
                'amount' => 5000,
                'currency' => 'TZS',
                'status_code' => 2,
                'payment_status_description' => 'Failed',
                'merchant_reference' => $tx->reference,
                'status' => '200',
            ], 200),
        ]);

        app(PesapalPaymentProcessor::class)->processNotification([
            'OrderTrackingId' => 'track-fail-1',
            'OrderMerchantReference' => $tx->reference,
        ]);

        $this->assertSame('failed', $order->fresh()->payment_status);
        $this->assertSame(0, PaymentStatusHistory::where('to_status', 'paid')->count());
    }

    public function test_initializing_pesapal_is_not_payment_successful(): void
    {
        Event::fake([PaymentSuccessful::class]);
        $this->enablePesapalSandbox();
        $this->fakePesapalHappyPath('track-init-only');
        [, $order, $tx] = $this->unpaidOrder();

        $result = app(PesapalGateway::class)->initializePayment($order, $tx);

        $this->assertSame(GatewayInitializationResult::STATUS_REQUIRES_ACTION, $result->status);
        $this->assertFalse($result->claimsPaymentSuccess());
        $this->assertSame('pending', $order->fresh()->payment_status);
        Event::assertNotDispatched(PaymentSuccessful::class);
    }

    public function test_missing_payment_url_handled_safely(): void
    {
        $this->enablePesapalSandbox();
        Http::fake([
            '*/api/Auth/RequestToken' => Http::response(['token' => 'tok', 'status' => '200'], 200),
            '*/api/URLSetup/RegisterIPN' => Http::response(['ipn_id' => 'ipn-1', 'status' => '200'], 200),
            '*/api/Transactions/SubmitOrderRequest' => Http::response([
                'order_tracking_id' => 'track-no-url',
                'redirect_url' => '',
                'status' => '200',
            ], 200),
        ]);

        [, $order, $tx] = $this->unpaidOrder();
        $result = app(PesapalGateway::class)->initializePayment($order, $tx);

        $this->assertSame(GatewayInitializationResult::STATUS_FAILED, $result->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_no_secrets_in_customer_payment_views(): void
    {
        $this->enablePesapalSandbox();
        [$customer, $order] = $this->unpaidOrder();

        $this->actingAs($customer)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertDontSee('test-secret')
            ->assertDontSee('consumer_secret')
            ->assertDontSee('sandbox-token');
    }
}
