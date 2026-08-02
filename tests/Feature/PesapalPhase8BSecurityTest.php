<?php

namespace Tests\Feature;

use App\Events\PaymentFailed;
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
use App\Support\Payments\PesapalGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesMarketplace;
use Tests\TestCase;

/**
 * Phase 8B: PesaPal sandbox security hardening (C1/C2/C3 + related).
 */
class PesapalPhase8BSecurityTest extends TestCase
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
        $customer = User::factory()->create(['email' => 'p8b-'.uniqid().'@example.com']);
        [, $store] = $this->createVendorUser(['email' => 'p8b-v-'.uniqid().'@example.com']);
        $product = $this->createProductForVendor($store, ['price' => 5000]);

        $order = Order::create([
            'order_number' => 'SN-P8B-'.uniqid(),
            'user_id' => $customer->id,
            'total_price' => $total,
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'pesapal',
            'shipping_method' => 'pickup',
            'shipping_address' => [
                'full_name' => 'Pesa Buyer',
                'phone' => '+255700000901',
                'line1' => '1 Street',
                'city' => 'Dar es Salaam',
            ],
        ]);
        OrderItem::recordPurchase($order->id, $product->id, 1, 5000);

        $tx = app(PaymentService::class)->ensurePendingTransaction($order, 'pesapal');

        return [$customer, $order->fresh(), $tx->fresh()];
    }

    protected function bindLocalTracking(PaymentTransaction $tx, string $trackingId): PaymentTransaction
    {
        $meta = $tx->metadata ?? [];
        $meta['pesapal'] = [
            'order_tracking_id' => $trackingId,
            'merchant_reference' => $tx->reference,
            'environment' => 'sandbox',
        ];
        $tx->metadata = $meta;
        $tx->save();

        return $tx->fresh();
    }

    protected function fakeStatus(
        string $merchantReference,
        int $statusCode = 1,
        float|int|string $amount = 5000,
        string $currency = 'TZS',
        ?string $description = 'Completed',
        ?string $merchantReferenceOverride = '__use_arg__',
    ): void {
        $ref = $merchantReferenceOverride === '__use_arg__' ? $merchantReference : $merchantReferenceOverride;

        Http::fake([
            '*/api/Auth/RequestToken' => Http::response(['token' => 'tok', 'status' => '200'], 200),
            '*/api/Transactions/GetTransactionStatus*' => Http::response([
                'payment_method' => 'MPESA',
                'amount' => $amount,
                'currency' => $currency,
                'status_code' => $statusCode,
                'payment_status_description' => $description,
                'merchant_reference' => $ref,
                'status' => '200',
            ], 200),
        ]);
    }

    public function test_a_correct_merchant_reference_wrong_tracking_id_is_rejected(): void
    {
        Event::fake([PaymentSuccessful::class, PaymentFailed::class]);
        $this->enablePesapalSandbox();
        [, $order, $tx] = $this->unpaidOrder();
        $this->bindLocalTracking($tx, 'local-track-correct');
        $this->fakeStatus($tx->reference, 1);

        $result = app(PesapalPaymentProcessor::class)->processNotification([
            'OrderTrackingId' => 'foreign-track-wrong',
            'OrderMerchantReference' => $tx->reference,
            'OrderNotificationType' => 'IPNCHANGE',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame(0, PaymentStatusHistory::query()->count());
        Event::assertNotDispatched(PaymentSuccessful::class);
        Event::assertNotDispatched(PaymentFailed::class);
    }

    public function test_b_missing_local_tracking_id_is_rejected(): void
    {
        Event::fake([PaymentSuccessful::class, PaymentFailed::class]);
        $this->enablePesapalSandbox();
        [, $order, $tx] = $this->unpaidOrder();
        $this->fakeStatus($tx->reference, 1);

        $result = app(PesapalPaymentProcessor::class)->processNotification([
            'OrderTrackingId' => 'track-no-local',
            'OrderMerchantReference' => $tx->reference,
            'OrderNotificationType' => 'IPNCHANGE',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame(0, PaymentStatusHistory::query()->count());
        Event::assertNotDispatched(PaymentSuccessful::class);
        Event::assertNotDispatched(PaymentFailed::class);
    }

    public function test_c_empty_provider_merchant_reference_is_rejected(): void
    {
        Event::fake([PaymentSuccessful::class, PaymentFailed::class]);
        $this->enablePesapalSandbox();
        [, $order, $tx] = $this->unpaidOrder();
        $this->bindLocalTracking($tx, 'track-empty-ref');
        $this->fakeStatus($tx->reference, 1, 5000, 'TZS', 'Completed', null);

        $result = app(PesapalPaymentProcessor::class)->processNotification([
            'OrderTrackingId' => 'track-empty-ref',
            'OrderMerchantReference' => $tx->reference,
            'OrderNotificationType' => 'IPNCHANGE',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame(0, PaymentStatusHistory::query()->count());
        Event::assertNotDispatched(PaymentSuccessful::class);
        Event::assertNotDispatched(PaymentFailed::class);
    }

    public function test_d_mismatched_provider_merchant_reference_is_rejected(): void
    {
        Event::fake([PaymentSuccessful::class, PaymentFailed::class]);
        $this->enablePesapalSandbox();
        [, $order, $tx] = $this->unpaidOrder();
        $this->bindLocalTracking($tx, 'track-mismatch-ref');
        $this->fakeStatus($tx->reference, 1, 5000, 'TZS', 'Completed', 'PAY-OTHER-REFERENCE');

        $result = app(PesapalPaymentProcessor::class)->processNotification([
            'OrderTrackingId' => 'track-mismatch-ref',
            'OrderMerchantReference' => $tx->reference,
            'OrderNotificationType' => 'IPNCHANGE',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame(0, PaymentStatusHistory::query()->count());
        Event::assertNotDispatched(PaymentSuccessful::class);
        Event::assertNotDispatched(PaymentFailed::class);
    }

    public function test_e_foreign_failed_tracking_id_cannot_fail_order(): void
    {
        Event::fake([PaymentSuccessful::class, PaymentFailed::class]);
        $this->enablePesapalSandbox();
        [, $order, $tx] = $this->unpaidOrder();
        $this->bindLocalTracking($tx, 'local-bound-track');

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

        $result = app(PesapalPaymentProcessor::class)->processNotification([
            'OrderTrackingId' => 'foreign-failed-track',
            'OrderMerchantReference' => $tx->reference,
            'OrderNotificationType' => 'IPNCHANGE',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertNotSame('failed', $order->fresh()->payment_status);
        $this->assertSame(0, PaymentStatusHistory::where('to_status', 'failed')->count());
        Event::assertNotDispatched(PaymentFailed::class);
    }

    public function test_f_bound_verified_status_code_1_marks_paid_via_payment_service(): void
    {
        Event::fake([PaymentSuccessful::class]);
        $this->enablePesapalSandbox();
        [, $order, $tx] = $this->unpaidOrder();
        $this->bindLocalTracking($tx, 'track-paid-bound');
        $this->fakeStatus($tx->reference, 1, 5000, 'TZS', 'Completed');

        $this->postJson(route('payments.pesapal.ipn'), [
            'OrderTrackingId' => 'track-paid-bound',
            'OrderMerchantReference' => $tx->reference,
            'OrderNotificationType' => 'IPNCHANGE',
        ])->assertOk()->assertJson(['status' => 200]);

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('track-paid-bound', $tx->fresh()->provider_reference);
        $this->assertSame(1, PaymentStatusHistory::where('to_status', 'paid')->count());
        Event::assertDispatched(PaymentSuccessful::class);
        $this->assertSame(1, PaymentNotificationReceipt::query()->count());
    }

    public function test_g_description_completed_without_status_code_1_is_not_paid(): void
    {
        Event::fake([PaymentSuccessful::class]);
        $this->enablePesapalSandbox();
        [, $order, $tx] = $this->unpaidOrder();
        $this->bindLocalTracking($tx, 'track-desc-only');
        $this->fakeStatus($tx->reference, 0, 5000, 'TZS', 'COMPLETED');

        $result = app(PesapalPaymentProcessor::class)->processNotification([
            'OrderTrackingId' => 'track-desc-only',
            'OrderMerchantReference' => $tx->reference,
            'OrderNotificationType' => 'IPNCHANGE',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame(0, PaymentStatusHistory::where('to_status', 'paid')->count());
        Event::assertNotDispatched(PaymentSuccessful::class);
    }

    public function test_h_duplicate_ipn_is_idempotent(): void
    {
        Event::fake([PaymentSuccessful::class]);
        $this->enablePesapalSandbox();
        [, $order, $tx] = $this->unpaidOrder();
        $this->bindLocalTracking($tx, 'track-dup');
        $this->fakeStatus($tx->reference, 1);

        $payload = [
            'OrderTrackingId' => 'track-dup',
            'OrderMerchantReference' => $tx->reference,
            'OrderNotificationType' => 'IPNCHANGE',
        ];

        $this->postJson(route('payments.pesapal.ipn'), $payload)->assertOk();
        $this->postJson(route('payments.pesapal.ipn'), $payload)->assertOk();

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(1, PaymentStatusHistory::where('to_status', 'paid')->count());
        $this->assertSame(1, PaymentNotificationReceipt::query()->count());
        Event::assertDispatchedTimes(PaymentSuccessful::class, 1);
    }

    public function test_i_malicious_redirect_url_is_rejected(): void
    {
        $this->enablePesapalSandbox();
        Http::fake([
            '*/api/Auth/RequestToken' => Http::response(['token' => 'tok', 'status' => '200'], 200),
            '*/api/URLSetup/RegisterIPN' => Http::response(['ipn_id' => 'ipn-1', 'status' => '200'], 200),
            '*/api/Transactions/SubmitOrderRequest' => Http::response([
                'order_tracking_id' => 'track-evil',
                'redirect_url' => 'https://evil.example.com/phish',
                'status' => '200',
            ], 200),
        ]);

        [$customer, $order, $tx] = $this->unpaidOrder();
        $result = app(PesapalGateway::class)->initializePayment($order, $tx);

        $this->assertSame(GatewayInitializationResult::STATUS_FAILED, $result->status);
        $this->assertArrayNotHasKey('redirect_url', $result->metadata);
        $this->assertFalse(app(PesapalGateway::class)->isAllowedRedirectUrl('https://evil.example.com/phish'));
        $this->assertFalse(app(PesapalGateway::class)->isAllowedRedirectUrl('http://cybqa.pesapal.com/pay'));

        $this->actingAs($customer)
            ->get(route('checkout.confirmation', $order))
            ->assertOk()
            ->assertDontSee('https://evil.example.com', false)
            ->assertDontSee('Continue to PesaPal');
    }

    public function test_j_callback_with_unbound_tracking_id_cannot_mutate_payment(): void
    {
        Event::fake([PaymentSuccessful::class, PaymentFailed::class]);
        $this->enablePesapalSandbox();
        [$customer, $order, $tx] = $this->unpaidOrder();
        $this->bindLocalTracking($tx, 'bound-only');
        $this->fakeStatus($tx->reference, 1);

        $this->actingAs($customer)
            ->get(route('payments.pesapal.callback', [
                'OrderTrackingId' => 'unbound-callback-track',
                'OrderMerchantReference' => $tx->reference,
                'OrderNotificationType' => 'CALLBACKURL',
            ]))
            ->assertRedirect();

        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame(0, PaymentStatusHistory::query()->count());
        Event::assertNotDispatched(PaymentSuccessful::class);
        Event::assertNotDispatched(PaymentFailed::class);
    }

    public function test_k_production_environment_still_fail_closed(): void
    {
        $this->enablePesapalSandbox();
        config(['payments.gateways.pesapal.environment' => 'production']);

        $this->assertFalse(app(PaymentGatewayManager::class)->gatewayAllowsLiveCharging('pesapal'));

        [, $order, $tx] = $this->unpaidOrder();
        $result = app(PesapalGateway::class)->initializePayment($order, $tx);

        $this->assertTrue($result->isComingSoon());
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertFalse($result->claimsPaymentSuccess());
    }

    public function test_l_missing_credentials_still_show_coming_soon_stub_behavior(): void
    {
        config([
            'payments.default' => 'pesapal',
            'payments.gateways.pesapal.enabled' => true,
            'payments.gateways.pesapal.live_charging' => true,
            'payments.gateways.pesapal.environment' => 'sandbox',
            'payments.gateways.pesapal.consumer_key' => '',
            'payments.gateways.pesapal.consumer_secret' => '',
        ]);
        Cache::flush();

        [, $order, $tx] = $this->unpaidOrder();
        $result = app(PaymentGatewayManager::class)->initialize($order, $tx);

        $this->assertTrue(
            $result->isComingSoon()
            || $result->status === GatewayInitializationResult::STATUS_COMING_SOON
            || $result->provider === 'stub'
        );
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertFalse($result->claimsPaymentSuccess());
        $this->assertFalse(app(PesapalGateway::class)->supportsLiveCharging());
    }

    public function test_submit_order_sends_decimal_string_amount_not_float_cast(): void
    {
        $this->enablePesapalSandbox();
        Http::fake([
            '*/api/Auth/RequestToken' => Http::response(['token' => 'tok', 'status' => '200'], 200),
            '*/api/URLSetup/RegisterIPN' => Http::response(['ipn_id' => 'ipn-1', 'status' => '200'], 200),
            '*/api/Transactions/SubmitOrderRequest' => Http::response([
                'order_tracking_id' => 'track-decimal',
                'redirect_url' => 'https://cybqa.pesapal.com/pesapaliframe/PesapalIframe3/Index/?OrderTrackingId=track-decimal',
                'status' => '200',
            ], 200),
        ]);

        [, $order, $tx] = $this->unpaidOrder('1000.50');
        $result = app(PesapalGateway::class)->initializePayment($order, $tx);

        $this->assertSame(GatewayInitializationResult::STATUS_REQUIRES_ACTION, $result->status);
        $this->assertSame('1000.50', $result->metadata['amount']);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'SubmitOrderRequest')) {
                return false;
            }
            $data = $request->data();

            return ($data['amount'] ?? null) === '1000.50'
                && ! is_float($data['amount'] ?? null);
        });
    }

    public function test_initialization_persists_local_tracking_id_binding(): void
    {
        $this->enablePesapalSandbox();
        Http::fake([
            '*/api/Auth/RequestToken' => Http::response(['token' => 'tok', 'status' => '200'], 200),
            '*/api/URLSetup/RegisterIPN' => Http::response(['ipn_id' => 'ipn-1', 'status' => '200'], 200),
            '*/api/Transactions/SubmitOrderRequest' => Http::response([
                'order_tracking_id' => 'track-bound-init',
                'redirect_url' => 'https://cybqa.pesapal.com/pesapaliframe/PesapalIframe3/Index/?OrderTrackingId=track-bound-init',
                'status' => '200',
            ], 200),
        ]);

        [, $order, $tx] = $this->unpaidOrder();
        app(PesapalGateway::class)->initializePayment($order, $tx);

        $fresh = $tx->fresh();
        $this->assertSame('track-bound-init', $fresh->metadata['pesapal']['order_tracking_id'] ?? null);
        $this->assertSame($fresh->reference, $fresh->metadata['pesapal']['merchant_reference'] ?? null);
    }
}
