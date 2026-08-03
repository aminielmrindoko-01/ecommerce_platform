<?php

namespace App\Services\Operations;

use App\Models\Chargeback;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorEntitlement;
use App\Services\Authorization\AuditLogger;
use App\Services\Finance\LedgerService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Internal chargeback case management + compensating ledger posts.
 *
 * CHARGEBACK INTEGRATION: INTERNAL ARCHITECTURE / NOT PROVIDER-CONNECTED
 *
 * A chargeback is NOT a customer refund — it is a provider/bank reclaim of funds.
 */
class ChargebackService
{
    /**
     * @var array<string, list<string>>
     */
    protected array $transitions = [
        'received' => ['under_review', 'accepted', 'won', 'lost', 'closed'],
        'under_review' => ['responded', 'accepted', 'won', 'lost', 'closed'],
        'responded' => ['accepted', 'won', 'lost', 'closed'],
        'accepted' => ['closed'],
        'lost' => ['closed'],
        'won' => ['closed'],
        'closed' => [],
    ];

    public function __construct(
        protected SettlementHoldService $holds,
        protected LedgerService $ledger,
        protected PaymentService $payments,
        protected AuditLogger $audit,
    ) {}

    public function receive(
        Order $order,
        string $amount,
        User $actor,
        ?string $providerReference = null,
        ?string $reason = null,
        ?int $vendorId = null,
    ): Chargeback {
        if (! $actor->hasPermission('chargebacks.manage') && ! $actor->hasPermission('chargebacks.create')) {
            throw new InvalidArgumentException('Missing chargeback permission.');
        }

        $amount = $this->payments->normalizeMoney($amount);
        if (bccomp($amount, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('Chargeback amount must be greater than zero.');
        }

        $provider = config('operations.chargebacks.provider', 'internal');
        $providerReference = $providerReference ? trim($providerReference) : null;

        return DB::transaction(function () use ($order, $amount, $actor, $providerReference, $reason, $vendorId, $provider) {
            if ($providerReference) {
                $existing = Chargeback::query()
                    ->where('provider', $provider)
                    ->where('provider_reference', $providerReference)
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    return $existing;
                }
            }

            /** @var PaymentTransaction|null $tx */
            $tx = PaymentTransaction::query()
                ->where('order_id', $order->id)
                ->whereIn('status', ['paid', 'partially_refunded', 'refunded'])
                ->latest('id')
                ->first();

            $vendorId = $vendorId ?: $order->items->first()?->owningVendorId();

            $cb = new Chargeback;
            $cb->forceFill([
                'reference' => 'CB-'.strtoupper(Str::random(10)),
                'order_id' => $order->id,
                'payment_transaction_id' => $tx?->id,
                'vendor_id' => $vendorId,
                'amount' => $amount,
                'currency' => $order->currency ?: config('finance.currency', 'TZS'),
                'status' => 'received',
                'provider' => $provider,
                'provider_reference' => $providerReference,
                'reason' => $reason,
                'received_at' => now(),
                'created_by' => $actor->id,
                'metadata' => [
                    'integration' => 'internal',
                    'live' => false,
                ],
            ])->save();

            if (config('operations.holds.auto_hold_on_chargeback', true) && $vendorId) {
                $holdAmount = $this->holdAmount((int) $vendorId, $order->id, $amount);
                $hold = $this->holds->create(
                    vendor: Vendor::query()->findOrFail($vendorId),
                    amount: $holdAmount,
                    reasonCode: 'chargeback',
                    actor: $actor,
                    orderId: $order->id,
                    sourceType: 'chargeback',
                    sourceId: (string) $cb->id,
                    reason: 'Chargeback '.$cb->reference,
                );
                $cb->forceFill(['settlement_hold_id' => $hold->id])->save();
            }

            $this->audit->log(
                action: 'CHARGEBACK_RECEIVED',
                actor: $actor,
                resourceType: 'chargeback',
                resourceId: $cb->id,
                newValues: [
                    'order_id' => $order->id,
                    'vendor_id' => $vendorId,
                    'amount' => $amount,
                    'currency' => $cb->currency,
                    'status' => 'received',
                    'provider' => $provider,
                ],
            );

            return $cb->fresh();
        });
    }

    public function updateStatus(Chargeback $chargeback, string $to, User $actor, ?string $reason = null): Chargeback
    {
        if (! $actor->hasPermission('chargebacks.manage') && ! $actor->hasPermission('chargebacks.resolve')) {
            throw new InvalidArgumentException('Missing chargeback permission.');
        }

        return DB::transaction(function () use ($chargeback, $to, $actor, $reason) {
            /** @var Chargeback $locked */
            $locked = Chargeback::query()->whereKey($chargeback->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;
            $allowed = $this->transitions[$from] ?? [];
            if (! in_array($to, $allowed, true)) {
                throw new InvalidArgumentException("Invalid chargeback transition {$from} → {$to}.");
            }

            $locked->forceFill([
                'status' => $to,
                'reason' => $reason ?: $locked->reason,
            ])->save();

            if (in_array($to, ['lost', 'accepted'], true) && ! $locked->ledger_transaction_id) {
                $tx = $this->postLossReversal($locked, $actor);
                $locked->forceFill([
                    'ledger_transaction_id' => $tx->id,
                    'resolved_at' => now(),
                    'resolved_by' => $actor->id,
                ])->save();
            }

            if (in_array($to, ['won', 'closed'], true) && $locked->settlement_hold_id) {
                $hold = $locked->settlementHold;
                if ($hold && $hold->isActive()) {
                    $this->holds->release($hold, $actor, 'Chargeback '.$to);
                }
                if ($to !== 'closed') {
                    $locked->forceFill([
                        'resolved_at' => now(),
                        'resolved_by' => $actor->id,
                    ])->save();
                }
            }

            if ($to === 'closed') {
                $locked->forceFill([
                    'resolved_at' => $locked->resolved_at ?: now(),
                    'resolved_by' => $actor->id,
                ])->save();
            }

            $this->audit->log(
                action: in_array($to, ['won', 'lost', 'accepted', 'closed'], true)
                    ? 'CHARGEBACK_RESOLVED'
                    : 'CHARGEBACK_UPDATED',
                actor: $actor,
                resourceType: 'chargeback',
                resourceId: $locked->id,
                oldValues: ['status' => $from],
                newValues: [
                    'status' => $to,
                    'amount' => (string) $locked->getAttributes()['amount'],
                    'currency' => $locked->currency,
                    'order_id' => $locked->order_id,
                    'vendor_id' => $locked->vendor_id,
                    'reason' => $reason,
                ],
            );

            return $locked->fresh();
        });
    }

    /**
     * Compensating ledger for lost/accepted chargeback (does not mutate history).
     */
    protected function postLossReversal(Chargeback $cb, User $actor): \App\Models\LedgerTransaction
    {
        $amount = $this->payments->normalizeMoney($cb->getAttributes()['amount'] ?? $cb->amount);
        $currency = $cb->currency ?: 'TZS';
        $orderId = (int) $cb->order_id;

        $ents = VendorEntitlement::query()
            ->where('order_id', $orderId)
            ->when($cb->vendor_id, fn ($q) => $q->where('vendor_id', $cb->vendor_id))
            ->lockForUpdate()
            ->get();

        $vendorNet = '0.00';
        $commission = '0.00';
        foreach ($ents as $ent) {
            $remainingNet = $ent->remainingNet();
            $remainingComm = bcsub(
                $this->payments->normalizeMoney($ent->commission_amount),
                $this->payments->normalizeMoney($ent->refunded_commission),
                2
            );
            if (bccomp($remainingComm, '0.00', 2) < 0) {
                $remainingComm = '0.00';
            }

            // Claw back remaining entitlement against chargeback (capped by remaining).
            $ent->refunded_net = bcadd($this->payments->normalizeMoney($ent->refunded_net), $remainingNet, 2);
            $ent->refunded_commission = bcadd($this->payments->normalizeMoney($ent->refunded_commission), $remainingComm, 2);
            $ent->refunded_gross = bcadd(
                $this->payments->normalizeMoney($ent->refunded_gross),
                bcadd($remainingNet, $remainingComm, 2),
                2
            );
            $ent->status = 'reversed';
            $ent->save();

            $vendorNet = bcadd($vendorNet, $remainingNet, 2);
            $commission = bcadd($commission, $remainingComm, 2);
        }

        // DR VENDOR_PAYABLE + DR PLATFORM_REVENUE (+ REFUND_LIABILITY residual) / CR PLATFORM_CASH
        $lines = [
            ['account' => 'PLATFORM_CASH', 'debit' => '0.00', 'credit' => $amount],
        ];
        if (bccomp($vendorNet, '0.00', 2) > 0 && $cb->vendor_id) {
            $lines[] = [
                'account' => 'VENDOR_PAYABLE',
                'debit' => $vendorNet,
                'credit' => '0.00',
                'vendor_id' => (int) $cb->vendor_id,
            ];
        }
        if (bccomp($commission, '0.00', 2) > 0) {
            $lines[] = [
                'account' => 'PLATFORM_REVENUE',
                'debit' => $commission,
                'credit' => '0.00',
            ];
        }

        $debitSum = '0.00';
        $creditSum = '0.00';
        foreach ($lines as $line) {
            $debitSum = bcadd($debitSum, $line['debit'], 2);
            $creditSum = bcadd($creditSum, $line['credit'], 2);
        }
        $delta = bcsub($creditSum, $debitSum, 2);
        if (bccomp($delta, '0.00', 2) > 0) {
            $lines[] = ['account' => 'REFUND_LIABILITY', 'debit' => $delta, 'credit' => '0.00'];
        } elseif (bccomp($delta, '0.00', 2) < 0) {
            $lines[] = ['account' => 'REFUND_LIABILITY', 'debit' => '0.00', 'credit' => bcmul($delta, '-1', 2)];
        }

        return $this->ledger->post([
            'type' => 'chargeback_reversal',
            'currency' => $currency,
            'description' => 'Chargeback reversal '.$cb->reference,
            'order_id' => $orderId,
            'payment_transaction_id' => $cb->payment_transaction_id,
            'actor' => $actor,
            'idempotency_key' => 'chargeback-reversal:'.$cb->id,
            'metadata' => [
                'chargeback_id' => $cb->id,
                'integration' => 'internal',
            ],
        ], $lines);
    }

    protected function holdAmount(int $vendorId, int $orderId, string $chargebackAmount): string
    {
        $sum = VendorEntitlement::query()
            ->where('order_id', $orderId)
            ->where('vendor_id', $vendorId)
            ->get()
            ->reduce(fn ($c, $e) => bcadd($c, $e->remainingNet(), 2), '0.00');

        if (bccomp($sum, '0.00', 2) <= 0) {
            return $this->payments->normalizeMoney($chargebackAmount);
        }

        return bccomp($sum, $chargebackAmount, 2) < 0 ? $sum : $chargebackAmount;
    }
}
