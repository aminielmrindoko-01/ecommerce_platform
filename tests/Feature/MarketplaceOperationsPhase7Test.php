<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Chargeback;
use App\Models\Dispute;
use App\Models\LedgerTransaction;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\SettlementHold;
use App\Models\User;
use App\Models\VendorEntitlement;
use App\Services\Authorization\PermissionResolver;
use App\Services\CheckoutIdempotencyService;
use App\Services\Finance\PayoutService;
use App\Services\Finance\VendorPayableService;
use App\Services\Operations\ChargebackService;
use App\Services\Operations\CommissionConfigService;
use App\Services\Operations\DisputeService;
use App\Services\Operations\ReturnService;
use App\Services\Orders\OrderService;
use App\Services\PaymentService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Support\CreatesMarketplace;
use Tests\TestCase;

class MarketplaceOperationsPhase7Test extends TestCase
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

    protected function placePayDeliver($customer, $product, int $qty = 1, string $ref = 'OPS-PAY'): \App\Models\Order
    {
        $order = app(OrderService::class)->place($customer, [
            $product->id => $qty,
        ], [
            'payment_method' => 'cod',
            'shipping_method' => 'pickup',
            'checkout_token' => app(CheckoutIdempotencyService::class)->issue($customer->id),
            'shipping_address' => [
                'full_name' => 'Buyer',
                'phone' => '+255700000030',
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

        $order = $order->fresh(['items']);
        foreach ($order->items as $item) {
            $item->forceFill(['fulfillment_status' => 'delivered'])->save();
        }

        return $order->fresh(['items']);
    }

    public function test_customer_can_return_own_partial_qty_and_not_others(): void
    {
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor, ['price' => 100000, 'stock' => 10]);
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');
        $other = $this->assign(User::factory()->create(), 'customer', 'customer');
        $order = $this->placePayDeliver($customer, $product, 2, 'RET-1');
        $item = $order->items->first();

        $ret = app(ReturnService::class)->request($order, $item, $customer, 1, 'Damaged box');
        $this->assertSame('requested', $ret->status);
        $this->assertSame(1, $ret->items->first()->quantity);
        $this->assertTrue(SettlementHold::query()->where('source_type', 'return_request')->where('status', 'active')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'RETURN_REQUESTED')->exists());

        $this->expectException(InvalidArgumentException::class);
        app(ReturnService::class)->request($order, $item, $other, 1, 'Not mine');
    }

    public function test_return_restocks_only_on_receive_and_refund_uses_existing_service(): void
    {
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor, ['price' => 50000, 'stock' => 5]);
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');
        $order = $this->placePayDeliver($customer, $product, 1, 'RET-2');
        $item = $order->items->first();
        $stockAfterPay = (int) $product->fresh()->stock;

        $vendorUser = $vendor->user;
        $this->assign($vendorUser, 'vendor', 'vendor');
        $finance = $this->assign(User::factory()->create(), 'finance_manager');

        $returns = app(ReturnService::class);
        $ret = $returns->request($order, $item, $customer, 1, 'Wrong size');
        $this->assertSame($stockAfterPay, (int) $product->fresh()->stock);

        $returns->approve($ret, $vendorUser->fresh());
        $this->assertSame($stockAfterPay, (int) $product->fresh()->stock);

        $returns->markReceived($ret->fresh(), $vendorUser->fresh(), true);
        $this->assertSame($stockAfterPay + 1, (int) $product->fresh()->stock);
        $this->assertTrue(AuditLog::query()->where('action', 'RETURN_RECEIVED')->exists());

        $returns->processRefund($ret->fresh(), $finance);
        $this->assertSame('refunded', $ret->fresh()->status);
        $this->assertNotNull($ret->fresh()->payment_refund_id);
        $this->assertTrue(AuditLog::query()->where('action', 'RETURN_REFUNDED')->exists());
        $this->assertTrue(LedgerTransaction::query()->where('type', 'refund_reversal')->exists());

        $ent = VendorEntitlement::query()->where('order_item_id', $item->id)->first();
        $this->assertGreaterThan(0, bccomp($ent->refunded_net, '0.00', 2));
    }

    public function test_duplicate_return_and_mass_assignment_blocked(): void
    {
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor, ['price' => 20000, 'stock' => 3]);
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');
        $order = $this->placePayDeliver($customer, $product, 1, 'RET-3');
        $item = $order->items->first();
        $returns = app(ReturnService::class);
        $returns->request($order, $item, $customer, 1, 'First');

        try {
            $returns->request($order, $item, $customer, 1, 'Second');
            $this->fail('Duplicate return should fail');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('remaining quantity', strtolower($e->getMessage()));
        }

        try {
            $model = new ReturnRequest;
            $model->fill(['status' => 'refunded', 'customer_id' => 999, 'vendor_id' => 999]);
            $this->fail('Mass assignment of return status should be blocked');
        } catch (\Illuminate\Database\Eloquent\MassAssignmentException $e) {
            $this->assertStringContainsString('status', $e->getMessage());
        }
    }

    public function test_dispute_isolation_and_hold_blocks_payout(): void
    {
        [, $vendorA] = $this->createVendorUser();
        [, $vendorB] = $this->createVendorUser();
        $productA = $this->createProductForVendor($vendorA, ['price' => 100000, 'stock' => 5]);
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');
        $order = $this->placePayDeliver($customer, $productA, 1, 'DSP-1');
        $item = $order->items->first();

        $dispute = app(DisputeService::class)->open($order, $customer, 'Not received', 'Tracking stuck', $item);
        $this->assertSame('open', $dispute->status);
        $this->assertTrue(SettlementHold::query()->where('reason_code', 'dispute')->where('status', 'active')->exists());

        $vendorBUser = $this->assign($vendorB->user, 'vendor', 'vendor');
        $this->actingAs($vendorBUser)
            ->get(route('vendor.disputes.show', $dispute))
            ->assertForbidden();

        $vendorAUser = $this->assign($vendorA->user, 'vendor', 'vendor');
        try {
            app(PayoutService::class)->request($vendorA, '1000.00', $vendorAUser, 'payout-blocked-1');
            $this->fail('Payout should be blocked by dispute hold');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('hold', strtolower($e->getMessage()));
        }

        $support = $this->assign(User::factory()->create(), 'customer_support');
        app(DisputeService::class)->resolve($dispute, $support, 'resolved_vendor', 'Vendor evidence accepted');
        $this->assertSame('released', SettlementHold::query()->where('source_type', 'dispute')->first()->status);
    }

    public function test_chargeback_posts_compensating_ledger_and_is_idempotent(): void
    {
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor, ['price' => 100000, 'stock' => 5]);
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');
        $order = $this->placePayDeliver($customer, $product, 1, 'CB-1');
        $finance = $this->assign(User::factory()->create(), 'finance_manager');

        $svc = app(ChargebackService::class);
        $a = $svc->receive($order, '100000.00', $finance, 'prov-cb-1', 'Fraud claim', $vendor->id);
        $b = $svc->receive($order, '100000.00', $finance, 'prov-cb-1', 'Fraud claim', $vendor->id);
        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, Chargeback::count());

        $svc->updateStatus($a, 'under_review', $finance);
        $svc->updateStatus($a->fresh(), 'lost', $finance, 'Lost dispute with bank');

        $this->assertTrue(LedgerTransaction::query()->where('type', 'chargeback_reversal')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'CHARGEBACK_RESOLVED')->exists());
        $ent = VendorEntitlement::query()->where('order_id', $order->id)->first();
        $this->assertSame('reversed', $ent->status);
    }

    public function test_commission_config_change_does_not_mutate_history(): void
    {
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor, ['price' => 100000, 'stock' => 5]);
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');
        $order = $this->placePayDeliver($customer, $product, 1, 'COM-1');
        $ent = VendorEntitlement::query()->where('order_id', $order->id)->first();
        $originalCommission = (string) $ent->getAttributes()['commission_amount'];

        $finance = $this->assign(User::factory()->create(), 'finance_manager');
        app(CommissionConfigService::class)->updatePlatform($finance, 'percentage', '0.15');
        $this->assertTrue(AuditLog::query()->where('action', 'COMMISSION_CONFIG_UPDATED')->exists());

        $ent->refresh();
        $this->assertSame(0, bccomp($originalCommission, (string) $ent->getAttributes()['commission_amount'], 2));

        $vendorUser = $this->assign($vendor->user, 'vendor', 'vendor');
        $this->expectException(InvalidArgumentException::class);
        app(CommissionConfigService::class)->updatePlatform($vendorUser, 'percentage', '0.01');
    }

    public function test_customer_and_vendor_http_isolation(): void
    {
        [, $vendorA] = $this->createVendorUser();
        [, $vendorB] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendorA, ['price' => 30000, 'stock' => 5]);
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');
        $other = $this->assign(User::factory()->create(), 'customer', 'customer');
        $order = $this->placePayDeliver($customer, $product, 1, 'HTTP-1');
        $item = $order->items->first();

        $ret = app(ReturnService::class)->request($order, $item, $customer, 1, 'HTTP return');
        $dispute = app(DisputeService::class)->open($order, $customer, 'HTTP dispute', 'Details here', $item);

        $this->actingAs($other)->get(route('account.returns.show', $ret))->assertForbidden();
        $this->actingAs($other)->get(route('account.disputes.show', $dispute))->assertForbidden();

        $this->assign($vendorB->user, 'vendor', 'vendor');
        $this->actingAs($vendorB->user->fresh())->get(route('vendor.returns.show', $ret))->assertForbidden();

        $this->assign($vendorA->user, 'vendor', 'vendor');
        $this->actingAs($vendorA->user->fresh())->get(route('vendor.returns.show', $ret))->assertOk();
    }

    public function test_support_cannot_process_payouts_or_commission(): void
    {
        $support = $this->assign(User::factory()->create(), 'customer_support');
        $this->assertFalse($support->hasPermission('payouts.process'));
        $this->assertFalse($support->hasPermission('commission.manage'));
        $this->assertFalse($support->hasPermission('ledger.view'));
        $this->assertTrue($support->hasPermission('returns.view'));
        $this->assertTrue($support->hasPermission('disputes.resolve'));

        $this->actingAs($support)
            ->get(route('admin.operations.returns'))
            ->assertOk();
    }

    public function test_auditor_is_read_only_on_operations(): void
    {
        $auditor = $this->assign(User::factory()->create(), 'auditor');
        $this->assertTrue($auditor->hasPermission('returns.view'));
        $this->assertFalse($auditor->hasPermission('returns.manage'));
        $this->assertFalse($auditor->hasPermission('disputes.resolve'));
        $this->assertFalse($auditor->hasPermission('chargebacks.manage'));
        $this->assertFalse($auditor->hasPermission('commission.manage'));
    }

    public function test_vendor_financial_status_blocks_payout(): void
    {
        [, $vendor] = $this->createVendorUser();
        $product = $this->createProductForVendor($vendor, ['price' => 80000, 'stock' => 5]);
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');
        $this->placePayDeliver($customer, $product, 1, 'FINSTAT-1');

        $vendor->forceFill(['financial_status' => 'payout_hold'])->save();
        $summary = app(VendorPayableService::class)->summaryForVendor($vendor->fresh());
        $this->assertSame('0.00', $summary['available']);

        $vendorUser = $this->assign($vendor->user, 'vendor', 'vendor');
        $this->expectException(InvalidArgumentException::class);
        app(PayoutService::class)->request($vendor->fresh(), '1000.00', $vendorUser, 'fin-hold-1');
    }

    public function test_multi_vendor_partial_return_only_affects_one_vendor_item(): void
    {
        [, $vendorA] = $this->createVendorUser();
        [, $vendorB] = $this->createVendorUser();
        $productA = $this->createProductForVendor($vendorA, ['name' => 'A', 'price' => 100000, 'stock' => 5]);
        $productB = $this->createProductForVendor($vendorB, ['name' => 'B', 'price' => 50000, 'stock' => 5]);
        $customer = $this->assign(User::factory()->create(), 'customer', 'customer');

        $order = app(OrderService::class)->place($customer, [
            $productA->id => 1,
            $productB->id => 1,
        ], [
            'payment_method' => 'cod',
            'shipping_method' => 'pickup',
            'checkout_token' => app(CheckoutIdempotencyService::class)->issue($customer->id),
            'shipping_address' => [
                'full_name' => 'Buyer',
                'phone' => '+255700000031',
                'line1' => 'A',
                'city' => 'Dar',
                'country' => 'Tanzania',
            ],
        ]);
        $finance = $this->assign(User::factory()->create(), 'finance_manager');
        $payments = app(PaymentService::class);
        $payments->ensurePendingTransaction($order, 'stub');
        $payments->transitionOrderPayment($order, 'processing', $finance);
        $payments->transitionOrderPayment($order->fresh(), 'paid', $finance, null, 'manual', 'MV-RET');
        foreach ($order->fresh()->items as $line) {
            $line->forceFill(['fulfillment_status' => 'delivered'])->save();
        }
        $order = $order->fresh(['items']);
        $itemA = $order->items->first(fn (OrderItem $i) => (int) $i->owningVendorId() === (int) $vendorA->id);

        $ret = app(ReturnService::class)->request($order, $itemA, $customer, 1, 'Only A');
        $this->assertSame((int) $vendorA->id, (int) $ret->vendor_id);
        $this->assertSame(1, $ret->items()->count());

        $this->assign($vendorA->user, 'vendor', 'vendor');
        app(ReturnService::class)->approve($ret, $vendorA->user->fresh());
        app(ReturnService::class)->markReceived($ret->fresh(), $vendorA->user->fresh(), false);
        app(ReturnService::class)->processRefund($ret->fresh(), $finance);

        $entA = VendorEntitlement::query()->where('order_item_id', $itemA->id)->first();
        $entB = VendorEntitlement::query()->where('vendor_id', $vendorB->id)->first();
        $this->assertGreaterThan(0, bccomp($entA->refunded_net, '0.00', 2));
        $this->assertSame(0, bccomp((string) $entB->refunded_net, '0.00', 2));
    }
}
