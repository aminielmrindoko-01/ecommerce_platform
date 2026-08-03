<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\PaymentNotificationReceipt;
use App\Models\PaymentReconciliation;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\CheckoutIdempotencyService;
use App\Services\Orders\OrderService;
use App\Services\PaymentService;
use App\Services\Payments\PaymentReconciliationService;
use App\Services\Payments\RefundService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMarketplace;
use Tests\TestCase;

/**
 * Phase 5: payment attempts, inventory settlement, refunds, idempotency, isolation.
 */
class PaymentsPhase5Test extends TestCase
{
    use CreatesMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    protected function assign(User $user, string $rbac, string $legacy = 'admin'): User
    {
        $user->forceFill(['role' => $legacy, 'is_active' => true])->save();
        $role = Role::query()->where('name', $rbac)->firstOrFail();
        $user->roles()->sync([$role->id]);
        app(PermissionResolver::class)->forget($user);

        return $user->fresh();
    }

    protected function placeOrder(User $customer, $product, int $qty = 1): Order
    {
        return app(OrderService::class)->place($customer, [
            $product->id => $qty,
        ], [
            'payment_method' => 'cod',
            'shipping_method' => 'pickup',
            'checkout_token' => app(CheckoutIdempotencyService::class)->issue($customer->id),
            'shipping_address' => [
                'full_name' => 'Buyer',
                'phone' => '+255700000010',
                'line1' => 'A',
                'city' => 'Dar',
                'country' => 'Tanzania',
            ],
        ]);
    }

    public function test_checkout_reserves_inventory_and_payment_success_commits(): void
    {
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor, ['stock' => 5, 'price' => 10000]);
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');
        $admin = $this->assign(User::factory()->create(), 'finance_manager');

        $order = $this->placeOrder($customer, $product, 2);
        $product->refresh();

        $this->assertSame('reserved', $order->inventory_state);
        $this->assertSame(3, (int) $product->stock);
        $this->assertSame(2, (int) $product->reserved_quantity);
        $this->assertFalse(
            InventoryMovement::query()->where('product_id', $product->id)->where('type', 'sale')->exists()
        );

        $payments = app(PaymentService::class);
        $payments->ensurePendingTransaction($order, 'stub');
        $payments->transitionOrderPayment($order, 'processing', $admin);
        $payments->transitionOrderPayment($order->fresh(), 'paid', $admin, null, 'manual', 'REF-OK-1');

        $product->refresh();
        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('confirmed', $order->status);
        $this->assertSame('committed', $order->inventory_state);
        $this->assertSame(3, (int) $product->stock);
        $this->assertSame(0, (int) $product->reserved_quantity);
        $this->assertTrue(
            InventoryMovement::query()->where('product_id', $product->id)->where('type', 'sale')->exists()
        );
        $this->assertTrue(
            AuditLog::query()->where('action', 'PAYMENT_SUCCEEDED')->exists()
        );
    }

    public function test_payment_failure_releases_reservation_and_duplicate_paid_does_not_double_commit(): void
    {
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor, ['stock' => 2, 'price' => 8000]);
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');
        $admin = $this->assign(User::factory()->create(), 'finance_manager');
        $payments = app(PaymentService::class);

        $order = $this->placeOrder($customer, $product, 1);
        $this->assertSame(1, (int) $product->fresh()->stock);
        $this->assertSame(1, (int) $product->fresh()->reserved_quantity);

        $payments->ensurePendingTransaction($order, 'stub');
        $payments->transitionOrderPayment($order, 'failed', $admin, 'Gateway declined', 'manual', 'FAIL-1');

        $product->refresh();
        $this->assertSame(2, (int) $product->stock);
        $this->assertSame(0, (int) $product->reserved_quantity);
        $this->assertSame('released', $order->fresh()->inventory_state);
        $this->assertTrue(AuditLog::query()->where('action', 'PAYMENT_FAILED')->exists());

        // New attempt re-reserves, then paid commits once; replay paid is idempotent.
        $attempt2 = $payments->createAttempt($order->fresh(), 'stub', 'idem-retry-1');
        $this->assertSame(2, (int) $attempt2->attempt_number);
        $this->assertSame(1, (int) $product->fresh()->reserved_quantity);

        $payments->transitionOrderPayment($order->fresh(), 'processing', $admin);
        $payments->transitionOrderPayment($order->fresh(), 'paid', $admin, null, 'manual', 'OK-2');
        $saleCount = InventoryMovement::query()->where('product_id', $product->id)->where('type', 'sale')->count();

        $payments->transitionOrderPayment($order->fresh(), 'paid', $admin, null, 'manual', 'OK-2');
        $this->assertSame(
            $saleCount,
            InventoryMovement::query()->where('product_id', $product->id)->where('type', 'sale')->count()
        );
        $this->assertSame('committed', $order->fresh()->inventory_state);
    }

    public function test_idempotency_key_returns_same_payment_attempt(): void
    {
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor, ['stock' => 3]);
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');
        $order = $this->placeOrder($customer, $product);
        $payments = app(PaymentService::class);

        $a = $payments->ensurePendingTransaction($order, 'stub', 'client-key-abc');
        $b = $payments->ensurePendingTransaction($order, 'stub', 'client-key-abc');

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, PaymentTransaction::where('order_id', $order->id)->count());
    }

    public function test_customer_cannot_mark_payment_paid_or_view_others_payments(): void
    {
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor, ['stock' => 3]);
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');
        $other = $this->assign(User::factory()->create(), 'customer', 'customer');
        $order = $this->placeOrder($customer, $product);
        $tx = app(PaymentService::class)->ensurePendingTransaction($order, 'stub');

        $this->actingAs($customer)
            ->patch(route('admin.orders.payment', $order), [
                'payment_status' => 'paid',
                'provider_reference' => 'HACK',
            ])
            ->assertStatus(403);

        $this->actingAs($other)
            ->get(route('account.payments'))
            ->assertOk()
            ->assertDontSee($tx->reference);

        $this->actingAs($customer)
            ->get(route('account.payments'))
            ->assertOk()
            ->assertSee($tx->reference);
    }

    public function test_amount_and_currency_are_server_authoritative(): void
    {
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor, ['stock' => 2, 'price' => 15000]);
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');
        $order = $this->placeOrder($customer, $product);
        $tx = app(PaymentService::class)->ensurePendingTransaction($order, 'stub');

        $this->assertSame('TZS', $tx->currency);
        $this->assertSame('TZS', $order->currency);
        $this->assertSame(
            app(PaymentService::class)->authoritativeAmount($order),
            app(PaymentService::class)->normalizeMoney($tx->amount)
        );

        // Tamper stored amount then refuse transition.
        $tx->amount = '1.00';
        $tx->save();

        $admin = $this->assign(User::factory()->create(), 'finance_manager');
        $this->expectException(\InvalidArgumentException::class);
        app(PaymentService::class)->transitionOrderPayment($order, 'processing', $admin);
    }

    public function test_partial_refund_and_excessive_refund_rejected(): void
    {
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor, ['stock' => 2, 'price' => 10000]);
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');
        $finance = $this->assign(User::factory()->create(), 'finance_manager');
        $order = $this->placeOrder($customer, $product);
        $payments = app(PaymentService::class);
        $payments->ensurePendingTransaction($order, 'stub');
        $payments->transitionOrderPayment($order, 'processing', $finance);
        $payments->transitionOrderPayment($order->fresh(), 'paid', $finance, null, 'manual', 'PAY-R1');
        $tx = $order->fresh()->latestPaymentTransaction;

        $refunds = app(RefundService::class);
        $refund = $refunds->refund($order->fresh(), '5000.00', $finance, 'Partial return');
        $this->assertSame('completed', $refund->status);
        $this->assertSame('partially_refunded', $tx->fresh()->status);
        $this->assertSame('5000.00', app(PaymentService::class)->normalizeMoney($tx->fresh()->refunded_amount));

        $this->expectException(\InvalidArgumentException::class);
        $refunds->refund($order->fresh(), '10000.00', $finance, 'Too much');
    }

    public function test_refund_requires_step_up_and_permission(): void
    {
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor, ['stock' => 2, 'price' => 10000]);
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');
        $finance = $this->assign(User::factory()->create(), 'finance_manager');
        $support = $this->assign(User::factory()->create(), 'customer_support');
        $order = $this->placeOrder($customer, $product);
        $payments = app(PaymentService::class);
        $payments->ensurePendingTransaction($order, 'stub');
        $payments->transitionOrderPayment($order, 'processing', $finance);
        $payments->transitionOrderPayment($order->fresh(), 'paid', $finance, null, 'manual', 'PAY-R2');
        $tx = $order->fresh()->latestPaymentTransaction;

        $this->actingAs($support)
            ->withSession(['auth.step_up_confirmed_at' => now()->timestamp])
            ->post(route('admin.payments.refund', $tx), [
                'amount' => '1000.00',
                'reason' => 'Support should not refund',
            ])
            ->assertStatus(403);

        $this->actingAs($finance)
            ->withSession(['auth.step_up_confirmed_at' => now()->timestamp - 10_000])
            ->post(route('admin.payments.refund', $tx), [
                'amount' => '1000.00',
                'reason' => 'Needs step-up',
            ])
            ->assertRedirect(route('security.step-up'));

        $this->assertSame(0, PaymentRefund::count());

        $this->actingAs($finance)
            ->withSession(['auth.step_up_confirmed_at' => now()->timestamp])
            ->post(route('admin.payments.refund', $tx), [
                'amount' => '1000.00',
                'reason' => 'Approved refund',
            ])
            ->assertRedirect();

        $this->assertSame(1, PaymentRefund::count());
        $this->assertTrue(AuditLog::query()->where('action', 'REFUND_COMPLETED')->exists());
    }

    public function test_order_manager_cannot_manage_payments(): void
    {
        $manager = $this->assign(User::factory()->create(), 'order_manager');
        $this->assertTrue($manager->hasPermission('orders.update'));
        $this->assertFalse($manager->hasPermission('payments.manage'));
        $this->assertFalse($manager->hasPermission('refunds.create'));
        $this->assertFalse($manager->hasPermission('payouts.process'));
    }

    public function test_vendor_cannot_access_admin_payments(): void
    {
        [$vendorUser] = $this->createVendorUser();
        $this->actingAs($vendorUser)
            ->get(route('admin.payments.index'))
            ->assertStatus(403);
    }

    public function test_reconciliation_flags_mismatch_without_mutating_payment(): void
    {
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor, ['stock' => 2]);
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');
        $order = $this->placeOrder($customer, $product);
        $tx = app(PaymentService::class)->ensurePendingTransaction($order, 'stub');

        app(PaymentReconciliationService::class)->flagMismatch(
            $tx,
            $order,
            'pending',
            'COMPLETED',
            'Provider reports success but local is pending',
        );

        $this->assertSame(1, PaymentReconciliation::count());
        $this->assertSame('pending', $tx->fresh()->status);
        $this->assertTrue(
            AuditLog::query()->where('action', 'PAYMENT_RECONCILIATION_REQUIRED')->exists()
        );
    }

    public function test_admin_payments_dashboard_is_permission_gated(): void
    {
        $finance = $this->assign(User::factory()->create(), 'finance_manager');
        $this->actingAs($finance)
            ->get(route('admin.payments.index'))
            ->assertOk();
    }

    public function test_notification_receipt_replay_table_still_unique(): void
    {
        PaymentNotificationReceipt::query()->create([
            'provider' => 'pesapal',
            'notification_key' => 'pesapal:abc',
            'merchant_reference' => 'PAY-TEST',
            'tracking_id' => 'TRK1',
            'notification_type' => 'IPNCHANGE',
            'received_at' => now(),
            'processing_status' => 'processed',
            'processed_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        PaymentNotificationReceipt::query()->create([
            'provider' => 'pesapal',
            'notification_key' => 'pesapal:abc',
            'merchant_reference' => 'PAY-TEST',
            'tracking_id' => 'TRK1',
            'notification_type' => 'IPNCHANGE',
            'received_at' => now(),
            'processing_status' => 'received',
        ]);
    }
}
