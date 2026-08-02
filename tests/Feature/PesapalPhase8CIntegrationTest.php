<?php

namespace Tests\Feature;

use App\Events\PaymentSuccessful;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentNotificationReceipt;
use App\Models\PaymentStatusHistory;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\PaymentGatewayManager;
use App\Services\PaymentService;
use App\Services\PesapalPaymentProcessor;
use App\Support\Payments\GatewayInitializationResult;
use App\Support\Payments\PesapalClient;
use App\Support\Payments\PesapalGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesMarketplace;
use Tests\TestCase;

/**
 * Phase 8C offline integration tests (HTTP fakes only).
 *
 * These are NOT real PesaPal Sandbox E2E tests. Real sandbox verification lives
 * under tests/Sandbox and is opt-in via PESAPAL_E2E=true + credentials.
 */
class PesapalPhase8CIntegrationTest extends TestCase
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
            'payments.gateways.pesapal.allowed_redirect_hosts' => ['cybqa.pesapal.com'],
        ]);
        Cache::flush();
    }

    /**
     * @return array{0: User, 1: Order, 2: PaymentTransaction}
     */
    protected function unpaidOrder(string $total = '5000.00'): array
    {
        $customer = User::factory()->create(['email' => 'p8c-'.uniqid().'@example.com']);
        [, $store] = $this->createVendorUser(['email' => 'p8c-v-'.uniqid().'@example.com']);
        $product = $this->createProductForVendor($store, ['price' => 5000]);

        $order = Order::create([
            'order_number' => 'SN-P8C-'.uniqid(),
            'user_id' => $customer->id,
            'total_price' => $total,
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'pesapal',
            'shipping_method' => 'pickup',
            'shipping_address' => [
                'full_name' => 'Pesa Buyer',
                'phone' => '+255700000902',
                'line1' => '1 Street',
                'city' => 'Dar es Salaam',
            ],
        ]);
        OrderItem::recordPurchase($order->id, $product->id, 1, 5000);

        $tx = app(PaymentService::class)->ensurePendingTransaction($order, 'pesapal');

        return [$customer, $order->fresh(), $tx->fresh()];
    }

    public function test_offline_full_lifecycle_init_bind_ipn_paid_via_payment_service(): void
    {
        Event::fake([PaymentSuccessful::class]);
        $this->enablePesapalSandbox();

        [$customer, $order, $tx] = $this->unpaidOrder();

        Http::fake([
            '*/api/Auth/RequestToken' => Http::response(['token' => 'tok', 'status' => '200'], 200),
            '*/api/URLSetup/RegisterIPN' => Http::response(['ipn_id' => 'ipn-8c', 'status' => '200'], 200),
            '*/api/Transactions/SubmitOrderRequest' => Http::response([
                'order_tracking_id' => 'track-8c-e2e',
                'redirect_url' => 'https://cybqa.pesapal.com/pesapaliframe/PesapalIframe3/Index/?OrderTrackingId=track-8c-e2e',
                'status' => '200',
            ], 200),
            '*/api/Transactions/GetTransactionStatus*' => Http::response([
                'amount' => 5000,
                'currency' => 'TZS',
                'status_code' => 1,
                'payment_status_description' => 'Completed',
                'merchant_reference' => $tx->reference,
                'status' => '200',
            ], 200),
        ]);

        $init = app(PaymentGatewayManager::class)->initialize($order, $tx);
        $this->assertSame(GatewayInitializationResult::STATUS_REQUIRES_ACTION, $init->status);
        $this->assertFalse($init->claimsPaymentSuccess());
        $this->assertSame('Continue to PesaPal', $init->headline);
        $this->assertSame('track-8c-e2e', $tx->fresh()->metadata['pesapal']['order_tracking_id'] ?? null);
        $this->assertTrue(app(PesapalGateway::class)->isAllowedRedirectUrl($init->metadata['redirect_url'] ?? null));

        $this->actingAs($customer)
            ->get(route('checkout.confirmation', $order))
            ->assertOk()
            ->assertSee('Continue to PesaPal')
            ->assertDontSee('Payment Successful')
            ->assertDontSee('test-secret');


        $this->postJson(route('payments.pesapal.ipn'), [
            'OrderTrackingId' => 'track-8c-e2e',
            'OrderMerchantReference' => $tx->reference,
            'OrderNotificationType' => 'IPNCHANGE',
        ])->assertOk()->assertJson(['status' => 200]);

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(1, PaymentStatusHistory::where('to_status', 'paid')->count());
        $this->assertSame(1, PaymentNotificationReceipt::query()->count());
        Event::assertDispatched(PaymentSuccessful::class);
    }

    public function test_configured_ipn_id_skips_register_ipn_call(): void
    {
        $this->enablePesapalSandbox();
        config(['payments.gateways.pesapal.ipn_id' => 'pre-registered-ipn']);

        Http::fake([
            '*/api/Auth/RequestToken' => Http::response(['token' => 'tok', 'status' => '200'], 200),
            '*/api/URLSetup/RegisterIPN' => Http::response(['ipn_id' => 'should-not-call', 'status' => '200'], 200),
            '*/api/Transactions/SubmitOrderRequest' => function ($request) {
                $data = $request->data();
                if (($data['notification_id'] ?? null) !== 'pre-registered-ipn') {
                    return Http::response(['error' => 'bad ipn'], 400);
                }

                return Http::response([
                    'order_tracking_id' => 'track-ipn-cfg',
                    'redirect_url' => 'https://cybqa.pesapal.com/pay',
                    'status' => '200',
                ], 200);
            },
        ]);

        [, $order, $tx] = $this->unpaidOrder();
        $result = app(PesapalGateway::class)->initializePayment($order, $tx);

        $this->assertSame(GatewayInitializationResult::STATUS_REQUIRES_ACTION, $result->status);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'RegisterIPN'));
    }

    public function test_sandbox_status_never_exposes_secrets(): void
    {
        $this->enablePesapalSandbox();
        $status = app(PesapalClient::class)->sandboxStatus();

        $encoded = json_encode($status);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('test-secret', $encoded);
        $this->assertStringNotContainsString('consumer_secret', $encoded);
        $this->assertTrue($status['credentials_configured']);
        $this->assertTrue($status['sandbox_only_allowed']);
    }

    public function test_artisan_sandbox_check_reports_missing_credentials_safely(): void
    {
        config([
            'payments.gateways.pesapal.environment' => 'sandbox',
            'payments.gateways.pesapal.consumer_key' => '',
            'payments.gateways.pesapal.consumer_secret' => '',
            'payments.gateways.pesapal.enabled' => false,
            'payments.gateways.pesapal.live_charging' => false,
        ]);

        $this->artisan('payments:pesapal-sandbox-check')
            ->expectsOutputToContain('Credentials missing')
            ->assertSuccessful();
    }

    public function test_artisan_sandbox_check_fail_closed_for_production_env(): void
    {
        config([
            'payments.gateways.pesapal.environment' => 'production',
            'payments.gateways.pesapal.consumer_key' => 'x',
            'payments.gateways.pesapal.consumer_secret' => 'y',
        ]);

        $this->artisan('payments:pesapal-sandbox-check')
            ->expectsOutputToContain('FAIL CLOSED')
            ->assertFailed();
    }

    public function test_callback_after_init_requires_server_verification_for_paid(): void
    {
        $this->enablePesapalSandbox();
        [$customer, $order, $tx] = $this->unpaidOrder();

        Http::fake([
            '*/api/Auth/RequestToken' => Http::response(['token' => 'tok', 'status' => '200'], 200),
            '*/api/URLSetup/RegisterIPN' => Http::response(['ipn_id' => 'ipn', 'status' => '200'], 200),
            '*/api/Transactions/SubmitOrderRequest' => Http::response([
                'order_tracking_id' => 'track-callback-8c',
                'redirect_url' => 'https://cybqa.pesapal.com/pay?t=1',
                'status' => '200',
            ], 200),
        ]);

        app(PesapalGateway::class)->initializePayment($order, $tx);

        Http::fake([
            '*/api/Auth/RequestToken' => Http::response(['token' => 'tok', 'status' => '200'], 200),
            '*/api/Transactions/GetTransactionStatus*' => Http::response([
                'amount' => 5000,
                'currency' => 'TZS',
                'status_code' => 0,
                'payment_status_description' => 'PENDING',
                'merchant_reference' => $tx->reference,
                'status' => '200',
            ], 200),
        ]);

        $this->actingAs($customer)
            ->get(route('payments.pesapal.callback', [
                'OrderTrackingId' => 'track-callback-8c',
                'OrderMerchantReference' => $tx->reference,
                'OrderNotificationType' => 'CALLBACKURL',
            ]))
            ->assertRedirect(route('checkout.confirmation', $order));

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_duplicate_notification_flag_is_idempotent_and_logged_path(): void
    {
        Event::fake([PaymentSuccessful::class]);
        $this->enablePesapalSandbox();
        [, $order, $tx] = $this->unpaidOrder();

        $meta = $tx->metadata ?? [];
        $meta['pesapal'] = [
            'order_tracking_id' => 'track-dup-8c',
            'merchant_reference' => $tx->reference,
            'environment' => 'sandbox',
        ];
        $tx->metadata = $meta;
        $tx->save();

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
            'OrderTrackingId' => 'track-dup-8c',
            'OrderMerchantReference' => $tx->reference,
            'OrderNotificationType' => 'IPNCHANGE',
        ];

        $first = app(PesapalPaymentProcessor::class)->processNotification($payload);
        $second = app(PesapalPaymentProcessor::class)->processNotification($payload);

        $this->assertTrue($first['ok']);
        $this->assertTrue($second['ok']);
        $this->assertTrue($second['duplicate'] ?? false);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(1, PaymentStatusHistory::where('to_status', 'paid')->count());
        $this->assertSame(1, PaymentNotificationReceipt::query()->count());
        Event::assertDispatchedTimes(PaymentSuccessful::class, 1);
    }

    public function test_default_gateway_remains_stub_without_sandbox_enablement(): void
    {
        $this->assertSame('stub', config('payments.default'));
        $this->assertFalse(app(PesapalGateway::class)->supportsLiveCharging());
    }
}
