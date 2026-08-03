<?php

namespace App\Services\Finance;

use App\Models\Order;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\VendorEntitlement;
use App\Services\Authorization\AuditLogger;
use App\Services\Operations\CommissionConfigService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates vendor entitlements + ledger posts when a payment succeeds.
 * Reverses proportionally on refunds.
 */
class VendorEntitlementService
{
    public function __construct(
        protected CommissionCalculator $commission,
        protected CommissionConfigService $commissionConfigs,
        protected LedgerService $ledger,
        protected PaymentService $payments,
        protected AuditLogger $audit,
    ) {}

    /**
     * Idempotent settlement for a paid payment transaction.
     */
    public function settlePaidPayment(PaymentTransaction $tx, ?User $actor = null): void
    {
        if (($tx->status ?: '') !== 'paid') {
            return;
        }

        $idempotencyKey = 'payment-settlement:'.$tx->id;

        DB::transaction(function () use ($tx, $actor, $idempotencyKey) {
            /** @var Order $order */
            $order = Order::query()->whereKey($tx->order_id)->lockForUpdate()->firstOrFail();
            $order->load(['items']);

            // Already settled?
            if (VendorEntitlement::query()->where('payment_transaction_id', $tx->id)->exists()) {
                return;
            }

            $currency = $order->currency ?: config('finance.currency', 'TZS');
            $holdHours = (int) config('finance.settlement_hold_hours', 0);
            $availableAt = $holdHours > 0 ? now()->addHours($holdHours) : now();

            $lines = [];
            $totalGross = '0.00';
            $totalCommission = '0.00';
            $totalNet = '0.00';
            $vendorNets = [];

            foreach ($order->items as $item) {
                $vendorId = $item->owningVendorId();
                if (! $vendorId) {
                    throw new InvalidArgumentException('Order item missing vendor identity for entitlement.');
                }

                $gross = $item->lineTotal();
                $override = $this->commissionConfigs->resolveForVendor($vendorId);
                $calc = $this->commission->forGross($gross, $override);

                $ent = new VendorEntitlement;
                $ent->forceFill([
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'vendor_id' => $vendorId,
                    'payment_transaction_id' => $tx->id,
                    'gross_amount' => $gross,
                    'commission_rate' => $calc['rate'],
                    'commission_type' => $calc['type'],
                    'commission_amount' => $calc['commission'],
                    'net_amount' => $calc['net'],
                    'refunded_gross' => '0.00',
                    'refunded_commission' => '0.00',
                    'refunded_net' => '0.00',
                    'currency' => $currency,
                    'status' => 'earned',
                    'available_at' => $availableAt,
                    'calculation_snapshot' => $calc['snapshot'],
                ])->save();

                $totalGross = bcadd($totalGross, $gross, 2);
                $totalCommission = bcadd($totalCommission, $calc['commission'], 2);
                $totalNet = bcadd($totalNet, $calc['net'], 2);
                $vendorNets[$vendorId] = bcadd($vendorNets[$vendorId] ?? '0.00', $calc['net'], 2);

                $this->audit->log(
                    action: 'VENDOR_ENTITLEMENT_CREATED',
                    actor: $actor,
                    resourceType: 'vendor_entitlement',
                    resourceId: $ent->id,
                    newValues: [
                        'vendor_id' => $vendorId,
                        'order_item_id' => $item->id,
                        'gross' => $gross,
                        'commission' => $calc['commission'],
                        'net' => $calc['net'],
                        'currency' => $currency,
                    ],
                );

                $this->audit->log(
                    action: 'COMMISSION_APPLIED',
                    actor: $actor,
                    resourceType: 'vendor_entitlement',
                    resourceId: $ent->id,
                    newValues: [
                        'type' => $calc['type'],
                        'rate' => $calc['rate'],
                        'commission' => $calc['commission'],
                        'currency' => $currency,
                    ],
                );
            }

            // Cash received may include tax/shipping beyond item subtotals.
            // Ledger settlement for entitlements is based on item subtotals;
            // residual (tax/shipping/discount delta) posts to PLATFORM_REVENUE.
            $paidAmount = $this->payments->normalizeMoney($tx->getAttributes()['amount'] ?? $tx->amount);
            $residual = bcsub($paidAmount, $totalGross, 2);

            $ledgerLines = [
                ['account' => 'PLATFORM_CASH', 'debit' => $paidAmount, 'credit' => '0.00'],
            ];

            foreach ($vendorNets as $vendorId => $net) {
                if (bccomp($net, '0.00', 2) <= 0) {
                    continue;
                }
                $ledgerLines[] = [
                    'account' => 'VENDOR_PAYABLE',
                    'debit' => '0.00',
                    'credit' => $net,
                    'vendor_id' => (int) $vendorId,
                ];
            }

            $platformCredit = bcadd($totalCommission, $residual, 2);
            if (bccomp($residual, '0.00', 2) < 0) {
                // Order-level discount: cash < item gross. Contra-revenue balances the journal.
                $discountAbs = bcmul($residual, '-1', 2);
                $ledgerLines[] = [
                    'account' => 'PLATFORM_REVENUE',
                    'debit' => $discountAbs,
                    'credit' => '0.00',
                ];
                if (bccomp($totalCommission, '0.00', 2) > 0) {
                    $ledgerLines[] = [
                        'account' => 'PLATFORM_REVENUE',
                        'debit' => '0.00',
                        'credit' => $totalCommission,
                    ];
                }
            } elseif (bccomp($platformCredit, '0.00', 2) > 0) {
                $ledgerLines[] = [
                    'account' => 'PLATFORM_REVENUE',
                    'debit' => '0.00',
                    'credit' => $platformCredit,
                ];
            }

            $this->ledger->post([
                'type' => 'payment_settlement',
                'currency' => $currency,
                'description' => 'Payment settlement for order '.$order->order_number,
                'order_id' => $order->id,
                'payment_transaction_id' => $tx->id,
                'actor' => $actor,
                'idempotency_key' => $idempotencyKey,
                'metadata' => [
                    'item_gross' => $totalGross,
                    'commission' => $totalCommission,
                    'vendor_net' => $totalNet,
                    'residual' => $residual,
                ],
            ], $ledgerLines);
        });
    }

    /**
     * Proportionally reverse entitlements for a completed refund (by item share of gross).
     */
    public function reverseForRefund(PaymentRefund $refund, ?User $actor = null): void
    {
        if (($refund->status ?: '') !== 'completed') {
            return;
        }

        $idempotencyKey = 'refund-reversal:'.$refund->id;

        DB::transaction(function () use ($refund, $actor, $idempotencyKey) {
            $existing = \App\Models\LedgerTransaction::query()
                ->where('idempotency_key', $idempotencyKey)
                ->exists();
            if ($existing) {
                return;
            }

            /** @var Order $order */
            $order = Order::query()->whereKey($refund->order_id)->lockForUpdate()->firstOrFail();
            $itemIds = $refund->metadata['order_item_ids'] ?? null;
            $entsQuery = VendorEntitlement::query()
                ->where('order_id', $order->id)
                ->lockForUpdate();
            if (is_array($itemIds) && $itemIds !== []) {
                $entsQuery->whereIn('order_item_id', $itemIds);
            }
            $ents = $entsQuery->get();

            if ($ents->isEmpty()) {
                return;
            }

            $refundAmount = $this->payments->normalizeMoney($refund->getAttributes()['amount'] ?? $refund->amount);
            $currency = $refund->currency ?: config('finance.currency', 'TZS');
            $itemScoped = is_array($itemIds) && $itemIds !== [];

            $totalGross = '0.00';
            foreach ($ents as $ent) {
                $remainingGross = bcsub(
                    $this->payments->normalizeMoney($ent->gross_amount),
                    $this->payments->normalizeMoney($ent->refunded_gross),
                    2
                );
                $totalGross = bcadd($totalGross, $this->nonNegative($remainingGross), 2);
            }

            if (bccomp($totalGross, '0.00', 2) <= 0) {
                throw new InvalidArgumentException('No remaining entitlement gross to reverse.');
            }

            $vendorNetReversals = [];
            $commissionReversal = '0.00';
            $allocated = '0.00';
            $lastIndex = $ents->count() - 1;

            foreach ($ents->values() as $i => $ent) {
                $remainingGross = bcsub(
                    $this->payments->normalizeMoney($ent->gross_amount),
                    $this->payments->normalizeMoney($ent->refunded_gross),
                    2
                );
                $remainingGross = $this->nonNegative($remainingGross);

                if ($itemScoped && $ents->count() === 1) {
                    // Return-linked refund: allocate exact refund amount to the item entitlement.
                    $shareGross = $refundAmount;
                } elseif ($i === $lastIndex) {
                    $shareGross = bcsub($refundAmount, $allocated, 2);
                } else {
                    $shareGross = bcdiv(bcmul($refundAmount, $remainingGross, 4), $totalGross, 2);
                    $allocated = bcadd($allocated, $shareGross, 2);
                }

                if (bccomp($shareGross, '0.00', 2) <= 0) {
                    continue;
                }
                if (bccomp($shareGross, $remainingGross, 2) > 0) {
                    $shareGross = $remainingGross;
                }

                // Use original snapshot rate for proportional commission clawback.
                $rate = bcadd((string) ($ent->getAttributes()['commission_rate'] ?? '0'), '0', 4);
                $shareCommission = bcmul($shareGross, $rate, 2);
                $shareNet = bcsub($shareGross, $shareCommission, 2);

                $ent->refunded_gross = bcadd($this->payments->normalizeMoney($ent->refunded_gross), $shareGross, 2);
                $ent->refunded_commission = bcadd($this->payments->normalizeMoney($ent->refunded_commission), $shareCommission, 2);
                $ent->refunded_net = bcadd($this->payments->normalizeMoney($ent->refunded_net), $shareNet, 2);

                $remainingNet = $ent->remainingNet();
                $ent->status = bccomp($remainingNet, '0.00', 2) <= 0 ? 'reversed' : 'partially_reversed';
                $ent->save();

                $vendorNetReversals[$ent->vendor_id] = bcadd(
                    $vendorNetReversals[$ent->vendor_id] ?? '0.00',
                    $shareNet,
                    2
                );
                $commissionReversal = bcadd($commissionReversal, $shareCommission, 2);
            }

            $ledgerLines = [
                ['account' => 'PLATFORM_CASH', 'debit' => '0.00', 'credit' => $refundAmount],
            ];
            foreach ($vendorNetReversals as $vendorId => $net) {
                if (bccomp($net, '0.00', 2) <= 0) {
                    continue;
                }
                $ledgerLines[] = [
                    'account' => 'VENDOR_PAYABLE',
                    'debit' => $net,
                    'credit' => '0.00',
                    'vendor_id' => (int) $vendorId,
                ];
            }
            if (bccomp($commissionReversal, '0.00', 2) > 0) {
                $ledgerLines[] = [
                    'account' => 'PLATFORM_REVENUE',
                    'debit' => $commissionReversal,
                    'credit' => '0.00',
                ];
            }

            // Balance residual (rounding) into PLATFORM_REVENUE.
            $debitSum = '0.00';
            $creditSum = '0.00';
            foreach ($ledgerLines as $line) {
                $debitSum = bcadd($debitSum, $line['debit'], 2);
                $creditSum = bcadd($creditSum, $line['credit'], 2);
            }
            $delta = bcsub($creditSum, $debitSum, 2);
            if (bccomp($delta, '0.00', 2) > 0) {
                $ledgerLines[] = ['account' => 'PLATFORM_REVENUE', 'debit' => $delta, 'credit' => '0.00'];
            } elseif (bccomp($delta, '0.00', 2) < 0) {
                $ledgerLines[] = ['account' => 'PLATFORM_REVENUE', 'debit' => '0.00', 'credit' => bcmul($delta, '-1', 2)];
            }

            $this->ledger->post([
                'type' => 'refund_reversal',
                'currency' => $currency,
                'description' => 'Refund ledger reversal '.$refund->reference,
                'order_id' => $order->id,
                'payment_transaction_id' => $refund->payment_transaction_id,
                'payment_refund_id' => $refund->id,
                'actor' => $actor,
                'idempotency_key' => $idempotencyKey,
            ], $ledgerLines);

            $this->audit->log(
                action: 'REFUND_LEDGER_CREATED',
                actor: $actor,
                resourceType: 'payment_refund',
                resourceId: $refund->id,
                newValues: [
                    'amount' => $refundAmount,
                    'currency' => $currency,
                    'order_id' => $order->id,
                ],
            );
        });
    }

    protected function nonNegative(string $amount): string
    {
        return bccomp($amount, '0.00', 2) < 0 ? '0.00' : $amount;
    }
}
