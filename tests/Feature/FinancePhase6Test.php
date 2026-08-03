<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Role;
use App\Models\User;
use App\Models\VendorEntitlement;
use App\Models\VendorPayout;
use App\Services\Authorization\PermissionResolver;
use App\Services\CheckoutIdempotencyService;
use App\Services\Finance\LedgerService;
use App\Services\Finance\PayoutService;
use App\Services\Finance\VendorPayableService;
use App\Services\Orders\OrderService;
use App\Services\PaymentService;
use App\Services\Payments\RefundService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Support\CreatesMarketplace;
use Tests\TestCase;

class FinancePhase6Test extends TestCase
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

    protected function placeAndPay($customer, $product, int $qty = 1, string $ref = 'FIN-PAY-1'): \App\Models\Order
    {
        $order = app(OrderService::class)->place($customer, [
            $product->id => $qty,
        ], [
            'payment_method' => 'cod',
            'shipping_method' => 'pickup',
            'checkout_token' => app(CheckoutIdempotencyService::class)->issue($customer->id),
            'shipping_address' => [
                'full_name' => 'Buyer',
                'phone' => '+255700000020',
                'line1' => 'A',
                'city' => 'Dar',
                'country' => 'Tanzania',
            ],
        ]);

        $finance = $this->assign(User::factory()->create(), 'finance_manager');
        $payments = app(PaymentService::class);
        $payments->ensurePendingTransaction($order, 'stub');
        $payments->transitionOrderPayment($order, 'processing', $finance);
        $payments->transitionOrderPayment($order->fresh(), 'paid', $finance, null, 'manual', $ref);

        return $order->fresh(['items']);
    }

    public function test_ledger_rejects_unbalanced_negative_and_invalid_account(): void
    {
        $ledger = app(LedgerService::class);

        try {
            $ledger->post(['type' => 'adjustment', 'currency' => 'TZS'], [
                ['account' => 'PLATFORM_CASH', 'debit' => '100.00', 'credit' => '0.00'],
                ['account' => 'PLATFORM_REVENUE', 'debit' => '0.00', 'credit' => '50.00'],
            ]);
            $this->fail('Expected unbalanced ledger to throw');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Unbalanced', $e->getMessage());
        }

        try {
            $ledger->post(['type' => 'adjustment', 'currency' => 'TZS'], [
                ['account' => 'PLATFORM_CASH', 'debit' => '-10.00', 'credit' => '0.00'],
            ]);
            $this->fail('Expected negative amount to throw');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('negative', $e->getMessage());
        }

        try {
            $ledger->post(['type' => 'adjustment', 'currency' => 'TZS'], [
                ['account' => 'NOT_A_REAL_ACCOUNT', 'debit' => '10.00', 'credit' => '0.00'],
                ['account' => 'PLATFORM_REVENUE', 'debit' => '0.00', 'credit' => '10.00'],
            ]);
            $this->fail('Expected invalid account to throw');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Invalid ledger account', $e->getMessage());
        }
    }

    public function test_ledger_posts_balanced_and_is_idempotent(): void
    {
        $ledger = app(LedgerService::class);
        $a = $ledger->post([
            'type' => 'adjustment',
            'currency' => 'TZS',
            'idempotency_key' => 'ledger-idem-1',
            'description' => 'Test',
        ], [
            ['account' => 'PLATFORM_CASH', 'debit' => '100.00', 'credit' => '0.00'],
            ['account' => 'PLATFORM_REVENUE', 'debit' => '0.00', 'credit' => '100.00'],
        ]);
        $b = $ledger->post([
            'type' => 'adjustment',
            'currency' => 'TZS',
            'idempotency_key' => 'ledger-idem-1',
            'description' => 'Test',
        ], [
            ['account' => 'PLATFORM_CASH', 'debit' => '100.00', 'credit' => '0.00'],
            ['account' => 'PLATFORM_REVENUE', 'debit' => '0.00', 'credit' => '100.00'],
        ]);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, LedgerTransaction::count());
        $debits = $a->entries->reduce(fn ($c, $e) => bcadd($c, (string) $e->debit, 2), '0.00');
        $credits = $a->entries->reduce(fn ($c, $e) => bcadd($c, (string) $e->credit, 2), '0.00');
        $this->assertSame(0, bccomp($debits, $credits, 2));
    }

    public function test_paid_multi_vendor_order_creates_separate_entitlements_and_ledger(): void
    {
        [, $vendorA] = $this->createVendorUser();
        [, $vendorB] = $this->createVendorUser();
        $productA = $this->createProductForVendor($vendorA, ['name' => 'A', 'price' => 100000, 'stock' => 5]);
        $productB = $this->createProductForVendor($vendorB, ['name' => 'B', 'price' => 50000, 'stock' => 5]);
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');
        $finance = $this->assign(User::factory()->create(), 'finance_manager');

        $order = app(\App\Services\Orders\OrderService::class)->place($customer, [
            $productA->id => 1,
            $productB->id => 1,
        ], [
            'payment_method' => 'cod',
            'shipping_method' => 'pickup',
            'checkout_token' => app(\App\Services\CheckoutIdempotencyService::class)->issue($customer->id),
            'shipping_address' => [
                'full_name' => 'Buyer', 'phone' => '+2557', 'line1' => 'A', 'city' => 'Dar', 'country' => 'Tanzania',
            ],
        ]);

        $payments = app(\App\Services\PaymentService::class);
        $payments->ensurePendingTransaction($order, 'stub');
        $payments->transitionOrderPayment($order, 'processing', $finance);
        $payments->transitionOrderPayment($order->fresh(), 'paid', $finance, null, 'manual', 'FIN6-1');

        $ents = VendorEntitlement::query()->where('order_id', $order->id)->get();
        $this->assertCount(2, $ents);
        $this->assertSame($vendorA->id, $ents->firstWhere('vendor_id', $vendorA->id)->vendor_id);
        $this->assertSame($vendorB->id, $ents->firstWhere('vendor_id', $vendorB->id)->vendor_id);

        $entA = $ents->firstWhere('vendor_id', $vendorA->id);
        $this->assertSame('100000.00', app(PaymentService::class)->normalizeMoney($entA->gross_amount));
        $this->assertSame('10000.00', app(PaymentService::class)->normalizeMoney($entA->commission_amount));
        $this->assertSame('90000.00', app(PaymentService::class)->normalizeMoney($entA->net_amount));

        $this->assertSame(1, LedgerTransaction::query()->where('type', 'payment_settlement')->count());
        $this->assertTrue(\App\Models\AuditLog::query()->where('action', 'VENDOR_ENTITLEMENT_CREATED')->exists());
        $this->assertTrue(\App\Models\AuditLog::query()->where('action', 'LEDGER_TRANSACTION_CREATED')->exists());

        $payableA = app(VendorPayableService::class)->summaryForVendor($vendorA);
        $payableB = app(VendorPayableService::class)->summaryForVendor($vendorB);
        $this->assertSame('90000.00', $payableA['available']);
        $this->assertSame('45000.00', $payableB['available']);
    }

    public function test_refund_reverses_vendor_payable_in_ledger(): void
    {
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor, ['price' => 100000, 'stock' => 3]);
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');
        $finance = $this->assign(User::factory()->create(), 'finance_manager');

        $order = app(\App\Services\Orders\OrderService::class)->place($customer, [
            $product->id => 1,
        ], [
            'payment_method' => 'cod',
            'shipping_method' => 'pickup',
            'checkout_token' => app(\App\Services\CheckoutIdempotencyService::class)->issue($customer->id),
            'shipping_address' => [
                'full_name' => 'Buyer', 'phone' => '+2557', 'line1' => 'A', 'city' => 'Dar', 'country' => 'Tanzania',
            ],
        ]);

        $payments = app(\App\Services\PaymentService::class);
        $payments->ensurePendingTransaction($order, 'stub');
        $payments->transitionOrderPayment($order, 'processing', $finance);
        $payments->transitionOrderPayment($order->fresh(), 'paid', $finance, null, 'manual', 'FIN6-REF');

        $before = app(VendorPayableService::class)->summaryForVendor($vendor);
        $this->assertSame('90000.00', $before['available']);

        app(\App\Services\Payments\RefundService::class)->refund($order->fresh(), '20000.00', $finance, 'Partial');

        $after = app(VendorPayableService::class)->summaryForVendor($vendor->fresh());
        // 20% of 100k gross → 18k net clawback at 10% commission
        $this->assertSame('72000.00', $after['available']);
        $this->assertTrue(\App\Models\AuditLog::query()->where('action', 'REFUND_LEDGER_CREATED')->exists());
        $this->assertSame(1, LedgerTransaction::query()->where('type', 'refund_reversal')->count());
    }

    public function test_concurrent_payout_requests_only_one_succeeds(): void
    {
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor, ['price' => 100000, 'stock' => 3]);
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');
        $finance = $this->assign(User::factory()->create(), 'finance_manager');
        [$vendorUser] = [$vendor->user];

        $order = app(\App\Services\Orders\OrderService::class)->place($customer, [
            $product->id => 1,
        ], [
            'payment_method' => 'cod',
            'shipping_method' => 'pickup',
            'checkout_token' => app(\App\Services\CheckoutIdempotencyService::class)->issue($customer->id),
            'shipping_address' => [
                'full_name' => 'Buyer', 'phone' => '+2557', 'line1' => 'A', 'city' => 'Dar', 'country' => 'Tanzania',
            ],
        ]);
        $payments = app(\App\Services\PaymentService::class);
        $payments->ensurePendingTransaction($order, 'stub');
        $payments->transitionOrderPayment($order, 'processing', $finance);
        $payments->transitionOrderPayment($order->fresh(), 'paid', $finance, null, 'manual', 'FIN6-PO');

        $service = app(PayoutService::class);
        $ok = 0;
        $fail = 0;
        try {
            $service->request($vendor->fresh(), '90000.00', $vendorUser, 'po-a');
            $ok++;
        } catch (\Throwable) {
            $fail++;
        }
        try {
            $service->request($vendor->fresh(), '90000.00', $vendorUser, 'po-b');
            $ok++;
        } catch (\Throwable) {
            $fail++;
        }

        $this->assertSame(1, $ok);
        $this->assertSame(1, $fail);
        $this->assertSame(1, VendorPayout::count());
    }

    public function test_payout_idempotency_key_dedupes(): void
    {
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor, ['price' => 100000, 'stock' => 2]);
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');
        $finance = $this->assign(User::factory()->create(), 'finance_manager');
        $vendorUser = $vendor->user;

        $order = app(\App\Services\Orders\OrderService::class)->place($customer, [
            $product->id => 1,
        ], [
            'payment_method' => 'cod',
            'shipping_method' => 'pickup',
            'checkout_token' => app(\App\Services\CheckoutIdempotencyService::class)->issue($customer->id),
            'shipping_address' => [
                'full_name' => 'Buyer', 'phone' => '+2557', 'line1' => 'A', 'city' => 'Dar', 'country' => 'Tanzania',
            ],
        ]);
        $payments = app(\App\Services\PaymentService::class);
        $payments->ensurePendingTransaction($order, 'stub');
        $payments->transitionOrderPayment($order, 'processing', $finance);
        $payments->transitionOrderPayment($order->fresh(), 'paid', $finance, null, 'manual', 'FIN6-IDEM');

        $service = app(PayoutService::class);
        $a = $service->request($vendor->fresh(), '10000.00', $vendorUser, 'same-key');
        $b = $service->request($vendor->fresh(), '10000.00', $vendorUser, 'same-key');
        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, VendorPayout::count());
    }

    public function test_vendor_isolation_on_finance_and_payout_amount(): void
    {
        [$vendorAUser, $vendorA] = $this->createVendorUser();
        [$vendorBUser, $vendorB] = $this->createVendorUser();
        $productA = $this->createProductForVendor($vendorA, ['price' => 100000, 'stock' => 2]);
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');
        $finance = $this->assign(User::factory()->create(), 'finance_manager');

        $order = app(\App\Services\Orders\OrderService::class)->place($customer, [
            $productA->id => 1,
        ], [
            'payment_method' => 'cod',
            'shipping_method' => 'pickup',
            'checkout_token' => app(\App\Services\CheckoutIdempotencyService::class)->issue($customer->id),
            'shipping_address' => [
                'full_name' => 'Buyer', 'phone' => '+2557', 'line1' => 'A', 'city' => 'Dar', 'country' => 'Tanzania',
            ],
        ]);
        $payments = app(\App\Services\PaymentService::class);
        $payments->ensurePendingTransaction($order, 'stub');
        $payments->transitionOrderPayment($order, 'processing', $finance);
        $payments->transitionOrderPayment($order->fresh(), 'paid', $finance, null, 'manual', 'FIN6-ISO');

        $this->actingAs($vendorBUser)->get(route('vendor.finance.index'))->assertOk()->assertDontSee('90,000');
        $this->actingAs($vendorAUser)->get(route('vendor.finance.index'))->assertOk()->assertSee('90,000');

        $this->expectException(InvalidArgumentException::class);
        app(PayoutService::class)->request($vendorA, '90000.00', $vendorBUser);
    }

    public function test_payout_lifecycle_and_ledger_deducts_payable(): void
    {
        config(['finance.payout_separation_of_duties' => true]);
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor, ['price' => 100000, 'stock' => 2]);
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');
        $approver = $this->assign(User::factory()->create(), 'finance_manager');
        $processor = $this->assign(User::factory()->create(), 'finance_manager');
        $vendorUser = $vendor->user;

        $order = app(\App\Services\Orders\OrderService::class)->place($customer, [
            $product->id => 1,
        ], [
            'payment_method' => 'cod',
            'shipping_method' => 'pickup',
            'checkout_token' => app(\App\Services\CheckoutIdempotencyService::class)->issue($customer->id),
            'shipping_address' => [
                'full_name' => 'Buyer', 'phone' => '+2557', 'line1' => 'A', 'city' => 'Dar', 'country' => 'Tanzania',
            ],
        ]);
        $payments = app(\App\Services\PaymentService::class);
        $payments->ensurePendingTransaction($order, 'stub');
        $payments->transitionOrderPayment($order, 'processing', $approver);
        $payments->transitionOrderPayment($order->fresh(), 'paid', $approver, null, 'manual', 'FIN6-PO1');

        $service = app(PayoutService::class);
        $payout = $service->request($vendor->fresh(), '50000.00', $vendorUser, 'po-key-1');
        $service->approve($payout, $approver);
        $service->process($payout->fresh(), $processor);

        $this->assertSame('completed', $payout->fresh()->status);
        $summary = app(VendorPayableService::class)->summaryForVendor($vendor->fresh());
        $this->assertSame('40000.00', $summary['available']);
        $this->assertSame('50000.00', $summary['paid_out']);
        $this->assertTrue(\App\Models\AuditLog::query()->where('action', 'PAYOUT_COMPLETED')->exists());
    }

    public function test_customer_and_other_vendor_cannot_access_finance(): void
    {
        [$vendorAUser] = $this->createVendorUser();
        [$vendorBUser] = $this->createVendorUser();
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');

        $this->actingAs($customer)->get(route('vendor.finance.index'))->assertStatus(403);
        $this->actingAs($vendorAUser)->get(route('admin.finance.ledger'))->assertStatus(403);
        $this->actingAs($vendorBUser)->get(route('vendor.finance.index'))->assertOk();
    }

    public function test_auditor_can_view_ledger_but_not_process_payouts(): void
    {
        $auditor = $this->assign(User::factory()->create(), 'auditor');
        $this->assertTrue($auditor->hasPermission('ledger.view'));
        $this->assertTrue($auditor->hasPermission('payouts.view'));
        $this->assertFalse($auditor->hasPermission('payouts.process'));
        $this->assertFalse($auditor->hasPermission('refunds.create'));
    }

    public function test_ledger_entries_cannot_be_mass_assigned_or_deleted_via_model_fillable(): void
    {
        $this->assertSame([], (new LedgerTransaction)->getFillable());
        $this->assertSame([], (new \App\Models\LedgerEntry)->getFillable());
        $this->assertSame([], (new VendorEntitlement)->getFillable());
        $this->assertSame([], (new VendorPayout)->getFillable());
    }
}
