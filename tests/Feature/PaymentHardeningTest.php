<?php

namespace Tests\Feature;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentStatusHistory;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Notifications\CustomerPaymentUpdated;
use App\Services\CheckoutIdempotencyService;
use App\Services\PaymentService;
use App\Support\Payments\StubPaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Tests\Support\CreatesMarketplace;
use Tests\TestCase;

class PaymentHardeningTest extends TestCase
{
    use CreatesMarketplace;
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Order, 2: PaymentTransaction}
     */
    protected function unpaidOrder(string|float $total = '5000.00'): array
    {
        $customer = User::factory()->create();
        [, $store] = $this->createVendorUser(['email' => 'hard-v-'.uniqid().'@example.com']);
        $product = $this->createProductForVendor($store, ['price' => 5000]);

        $order = Order::create([
            'order_number' => 'SN-HARD-'.uniqid(),
            'user_id' => $customer->id,
            'total_price' => $total,
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'mpesa',
            'shipping_method' => 'pickup',
        ]);
        OrderItem::recordPurchase($order->id, $product->id, 1, 5000);

        $tx = app(PaymentService::class)->ensurePendingTransaction($order, 'stub');

        return [$customer, $order->fresh(), $tx->fresh()];
    }

    public function test_legacy_order_status_endpoint_cannot_mark_paid(): void
    {
        [, $order] = $this->unpaidOrder();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.orders'))
            ->put(route('admin.orders.update', $order->id), [
                'status' => 'paid',
            ])
            ->assertSessionHasErrors('status');

        $order->refresh();
        $this->assertSame('pending', $order->status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame(0, PaymentStatusHistory::count());
    }

    public function test_payment_service_marks_paid_without_legacy_bypass(): void
    {
        Notification::fake();
        [$customer, $order] = $this->unpaidOrder();
        $admin = User::factory()->admin()->create();
        $service = app(PaymentService::class);

        $service->transitionOrderPayment($order, 'processing', $admin);
        $service->transitionOrderPayment($order->fresh(), 'paid', $admin, null, 'manual', 'PAY-OK-1');

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('confirmed', $order->status);
        Notification::assertSentTo($customer, CustomerPaymentUpdated::class);
    }

    public function test_paid_same_provider_reference_is_idempotent(): void
    {
        Notification::fake();
        [, $order] = $this->unpaidOrder();
        $admin = User::factory()->admin()->create();
        $service = app(PaymentService::class);

        $service->transitionOrderPayment($order, 'processing', $admin);
        $service->transitionOrderPayment($order->fresh(), 'paid', $admin, null, 'manual', 'SAME-REF');

        $historyBefore = PaymentStatusHistory::count();
        $txBefore = PaymentTransaction::count();

        $service->transitionOrderPayment($order->fresh(), 'paid', $admin, null, 'manual', 'SAME-REF');

        $this->assertSame($historyBefore, PaymentStatusHistory::count());
        $this->assertSame($txBefore, PaymentTransaction::count());
        Notification::assertSentTimes(CustomerPaymentUpdated::class, 1);
    }

    public function test_paid_different_provider_reference_is_rejected(): void
    {
        Notification::fake();
        [, $order] = $this->unpaidOrder();
        $admin = User::factory()->admin()->create();
        $service = app(PaymentService::class);

        $service->transitionOrderPayment($order, 'processing', $admin);
        $service->transitionOrderPayment($order->fresh(), 'paid', $admin, null, 'manual', 'ORIG-REF');

        $historyBefore = PaymentStatusHistory::count();

        try {
            $service->transitionOrderPayment($order->fresh(), 'paid', $admin, null, 'manual', 'OTHER-REF');
            $this->fail('Expected conflicting provider reference to be rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Conflicting provider reference', $e->getMessage());
        }

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('ORIG-REF', $order->latestPaymentTransaction->provider_reference);
        $this->assertSame($historyBefore, PaymentStatusHistory::count());
        Notification::assertSentTimes(CustomerPaymentUpdated::class, 1);
    }

    public function test_admin_payment_endpoint_surfaces_conflicting_reference_safely(): void
    {
        [, $order] = $this->unpaidOrder();
        $admin = User::factory()->admin()->create();
        $service = app(PaymentService::class);

        $service->transitionOrderPayment($order, 'processing', $admin);
        $service->transitionOrderPayment($order->fresh(), 'paid', $admin, null, 'manual', 'UI-REF');

        $this->actingAs($admin)
            ->from(route('admin.orders'))
            ->patch(route('admin.orders.payment', $order), [
                'payment_status' => 'paid',
                'provider_reference' => 'UI-REF-OTHER',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('UI-REF', $order->fresh()->latestPaymentTransaction->provider_reference);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('decimalAmountProvider')]
    public function test_decimal_safe_amounts_match_authoritative_total(string $amount): void
    {
        [, $order, $tx] = $this->unpaidOrder($amount);
        $service = app(PaymentService::class);
        $admin = User::factory()->admin()->create();

        $this->assertSame($amount, $service->normalizeMoney($amount));
        $this->assertSame($amount, $service->authoritativeAmount($order));
        $this->assertSame(0, bccomp($service->normalizeMoney($tx->amount), $amount, 2));

        $service->transitionOrderPayment($order, 'processing', $admin);
        $service->transitionOrderPayment($order->fresh(), 'paid', $admin);

        $this->assertSame($amount, $service->normalizeMoney($order->fresh()->total_price));
        $this->assertSame(0, bccomp($service->normalizeMoney($tx->fresh()->amount), $amount, 2));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function decimalAmountProvider(): array
    {
        return [
            '10.10' => ['10.10'],
            '100.25' => ['100.25'],
            '999.99' => ['999.99'],
            '1000.50' => ['1000.50'],
        ];
    }

    public function test_client_cannot_manipulate_payment_amount_via_admin_endpoint(): void
    {
        [, $order] = $this->unpaidOrder('100.25');
        $admin = User::factory()->admin()->create();
        $before = (string) $order->total_price;

        $this->actingAs($admin)
            ->patch(route('admin.orders.payment', $order), [
                'payment_status' => 'processing',
                'amount' => '1.00',
                'total_price' => '1.00',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame($before, (string) $order->total_price);
        $this->assertSame(0, bccomp(
            app(PaymentService::class)->normalizeMoney($order->latestPaymentTransaction->amount),
            '100.25',
            2
        ));
    }

    public function test_checkout_token_atomic_consume_allows_only_one_winner(): void
    {
        $user = User::factory()->create();
        $service = app(CheckoutIdempotencyService::class);
        $token = $service->issue($user);

        $first = $service->tryConsumeForTest($token, (int) $user->id);
        $second = $service->tryConsumeForTest($token, (int) $user->id);

        $this->assertTrue($first);
        $this->assertFalse($second);
    }

    public function test_duplicate_checkout_posts_create_only_one_order(): void
    {
        $user = User::factory()->create();
        [, $store] = $this->createVendorUser();
        $product = $this->createProductForVendor($store, ['price' => 10000, 'stock' => 5]);
        $token = app(CheckoutIdempotencyService::class)->issue($user);
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
            'full_name' => 'Hard Buyer',
            'phone' => '+255700000300',
            'line1' => '1 Street',
            'city' => 'Dar es Salaam',
            'payment_method' => 'cod',
            'shipping_method' => 'pickup',
            'checkout_token' => $token,
        ];

        $this->actingAs($user)->withSession(['cart' => $cart])->post(route('checkout.place'), $payload);
        $this->actingAs($user)->withSession(['cart' => $cart])->post(route('checkout.place'), $payload);
        $this->actingAs($user)->withSession(['cart' => $cart])->post(route('checkout.place'), $payload);

        $this->assertSame(1, Order::count());
        $this->assertSame(4, $product->fresh()->stock);
    }

    public function test_illegal_terminal_payment_transitions(): void
    {
        $admin = User::factory()->admin()->create();
        $service = app(PaymentService::class);

        [, $failed] = $this->unpaidOrder();
        $service->transitionOrderPayment($failed, 'failed', $admin, 'declined');
        try {
            $service->transitionOrderPayment($failed->fresh(), 'paid', $admin);
            $this->fail('failed → paid should be illegal');
        } catch (InvalidArgumentException) {
            $this->assertSame('failed', $failed->fresh()->payment_status);
        }

        [, $cancelled] = $this->unpaidOrder();
        $service->transitionOrderPayment($cancelled, 'cancelled', $admin, 'abandoned');
        try {
            $service->transitionOrderPayment($cancelled->fresh(), 'paid', $admin);
            $this->fail('cancelled → paid should be illegal');
        } catch (InvalidArgumentException) {
            $this->assertSame('cancelled', $cancelled->fresh()->payment_status);
        }

        [, $refunded] = $this->unpaidOrder();
        $service->transitionOrderPayment($refunded, 'processing', $admin);
        $service->transitionOrderPayment($refunded->fresh(), 'paid', $admin);
        $service->transitionOrderPayment($refunded->fresh(), 'refunded', $admin);
        try {
            $service->transitionOrderPayment($refunded->fresh(), 'paid', $admin);
            $this->fail('refunded → paid should be illegal');
        } catch (InvalidArgumentException) {
            $this->assertSame('refunded', $refunded->fresh()->payment_status);
        }

        try {
            $service->transitionOrderPayment($refunded->fresh(), 'pending', $admin);
            $this->fail('refunded → pending should be illegal');
        } catch (InvalidArgumentException) {
            $this->assertSame('refunded', $refunded->fresh()->payment_status);
        }
    }

    public function test_legal_payment_state_machine_path(): void
    {
        [, $order] = $this->unpaidOrder();
        $admin = User::factory()->admin()->create();
        $service = app(PaymentService::class);

        $service->transitionOrderPayment($order, 'processing', $admin);
        $this->assertSame('processing', $order->fresh()->payment_status);

        $service->transitionOrderPayment($order->fresh(), 'paid', $admin);
        $this->assertSame('paid', $order->fresh()->payment_status);

        $service->transitionOrderPayment($order->fresh(), 'partially_refunded', $admin);
        $this->assertSame('partially_refunded', $order->fresh()->payment_status);

        $service->transitionOrderPayment($order->fresh(), 'refunded', $admin);
        $this->assertSame('refunded', $order->fresh()->payment_status);
    }

    public function test_stub_gateway_does_not_auto_approve(): void
    {
        [, $order, $tx] = $this->unpaidOrder();
        $gateway = app(PaymentGatewayInterface::class);

        $this->assertInstanceOf(StubPaymentGateway::class, $gateway);
        $this->assertFalse($gateway->supportsLiveCharging());
        $init = $gateway->initializePayment($order, $tx);
        $this->assertSame('stub', $init->provider);
        $this->assertFalse($init->claimsPaymentSuccess());
        $this->assertTrue($init->isComingSoon());

        $result = $gateway->verifyPayment($tx, ['status' => 'success', 'amount' => '1']);
        $this->assertFalse($result->successful);
    }

    public function test_vendor_cannot_access_admin_payment_mutation(): void
    {
        [, $order] = $this->unpaidOrder();
        [$vendor] = $this->createVendorUser(['email' => 'hard-vendor@example.com']);

        $this->actingAs($vendor)
            ->patch(route('admin.orders.payment', $order), [
                'payment_status' => 'paid',
            ])
            ->assertStatus(403);

        $this->assertSame('pending', $order->fresh()->payment_status);
    }
}
