<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\PaymentRefund;
use App\Models\PaymentStatusHistory;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Authorization\AuditLogger;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Controlled refunds with cumulative amount tracking (manual/foundation path).
 * Provider-native refund APIs are not invented — records stay local until a
 * real gateway refund adapter is configured.
 */
class RefundService
{
    /**
     * @var array<string, list<string>>
     */
    protected array $transitions = [
        'requested' => ['approved', 'cancelled'],
        'approved' => ['processing', 'cancelled'],
        'processing' => ['completed', 'failed'],
        'completed' => [],
        'failed' => [],
        'cancelled' => [],
    ];

    public function __construct(
        protected PaymentService $payments,
        protected AuditLogger $audit,
    ) {}

    /**
     * Request + immediately complete a manual refund (foundation/admin path).
     * Uses step-up at the route layer for authorization.
     */
    public function refund(
        Order $order,
        string $amount,
        User $actor,
        string $reason,
        ?string $providerReference = null,
    ): PaymentRefund {
        if (! $actor->hasPermission('refunds.create') && ! $actor->hasPermission('orders.refund')) {
            throw new InvalidArgumentException('Missing refund permission.');
        }

        $amount = $this->payments->normalizeMoney($amount);
        if (bccomp($amount, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('Refund amount must be greater than zero.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A refund reason is required.');
        }

        return DB::transaction(function () use ($order, $amount, $actor, $reason, $providerReference) {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            /** @var PaymentTransaction|null $tx */
            $tx = PaymentTransaction::query()
                ->where('order_id', $lockedOrder->id)
                ->whereIn('status', ['paid', 'partially_refunded'])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $tx) {
                throw new InvalidArgumentException('No refundable paid transaction found for this order.');
            }

            if ($tx->currency !== ($lockedOrder->currency ?: 'TZS')) {
                throw new InvalidArgumentException('Currency mismatch.');
            }

            $paid = $this->payments->normalizeMoney($tx->getAttributes()['amount'] ?? $tx->amount);
            $already = $this->payments->normalizeMoney($tx->getAttributes()['refunded_amount'] ?? $tx->refunded_amount ?? '0');
            $remaining = bcsub($paid, $already, 2);

            if (bccomp($amount, $remaining, 2) > 0) {
                throw new InvalidArgumentException(
                    "Refund exceeds remaining refundable amount ({$remaining} {$tx->currency})."
                );
            }

            $refund = new PaymentRefund;
            $refund->forceFill([
                'payment_transaction_id' => $tx->id,
                'order_id' => $lockedOrder->id,
                'actor_user_id' => $actor->id,
                'reference' => $this->generateReference(),
                'amount' => $amount,
                'currency' => $tx->currency,
                'status' => 'requested',
                'provider_reference' => $providerReference,
                'reason' => $reason,
                'metadata' => ['source' => 'admin_manual'],
            ])->save();

            $this->audit->log(
                action: 'REFUND_REQUESTED',
                actor: $actor,
                resourceType: 'payment_refund',
                resourceId: $refund->id,
                newValues: [
                    'amount' => $amount,
                    'currency' => $tx->currency,
                    'order_id' => $lockedOrder->id,
                    'payment_transaction_id' => $tx->id,
                ],
                reason: $reason,
            );

            // Foundation path: approve → processing → completed in one controlled flow.
            $refund = $this->transition($refund, 'approved', $actor, $reason);
            $refund = $this->transition($refund, 'processing', $actor, $reason);
            $refund = $this->transition($refund, 'completed', $actor, $reason);

            $newRefunded = bcadd($already, $amount, 2);
            $tx->refunded_amount = $newRefunded;

            $nextPaymentStatus = bccomp($newRefunded, $paid, 2) >= 0
                ? 'refunded'
                : 'partially_refunded';

            $from = $tx->status;
            $tx->status = $nextPaymentStatus;
            $tx->completed_at = $tx->completed_at ?: now();
            $tx->save();

            $lockedOrder->payment_status = $nextPaymentStatus;
            $lockedOrder->save();

            PaymentStatusHistory::create([
                'payment_transaction_id' => $tx->id,
                'actor_user_id' => $actor->id,
                'from_status' => $from,
                'to_status' => $nextPaymentStatus,
                'reason' => $reason,
                'metadata' => [
                    'refund_id' => $refund->id,
                    'refund_amount' => $amount,
                    'refunded_amount_total' => $newRefunded,
                    'currency' => $tx->currency,
                    'reference' => $tx->reference,
                ],
            ]);

            $this->audit->log(
                action: 'REFUND_COMPLETED',
                actor: $actor,
                resourceType: 'payment_refund',
                resourceId: $refund->id,
                oldValues: ['payment_status' => $from, 'refunded_amount' => $already],
                newValues: [
                    'payment_status' => $nextPaymentStatus,
                    'refunded_amount' => $newRefunded,
                    'amount' => $amount,
                    'currency' => $tx->currency,
                ],
                reason: $reason,
            );

            return $refund->fresh(['paymentTransaction', 'order']);
        });
    }

    public function transition(PaymentRefund $refund, string $next, User $actor, ?string $reason = null): PaymentRefund
    {
        $next = strtolower(trim($next));
        if (! PaymentRefund::isValidStatus($next)) {
            throw new InvalidArgumentException('Invalid refund status.');
        }

        $current = $refund->status ?: 'requested';
        if ($current === $next) {
            return $refund;
        }

        if (! in_array($next, $this->transitions[$current] ?? [], true)) {
            throw new InvalidArgumentException("Cannot transition refund from {$current} to {$next}.");
        }

        $refund->status = $next;
        if ($next === 'completed') {
            $refund->completed_at = now();
        }
        $refund->save();

        $action = match ($next) {
            'approved' => 'REFUND_APPROVED',
            'completed' => 'REFUND_COMPLETED',
            'failed' => 'REFUND_FAILED',
            'cancelled' => 'REFUND_CANCELLED',
            default => 'REFUND_STATUS_CHANGED',
        };

        // Avoid duplicate REFUND_COMPLETED from outer refund() — only log intermediate here.
        if ($action !== 'REFUND_COMPLETED') {
            $this->audit->log(
                action: $action,
                actor: $actor,
                resourceType: 'payment_refund',
                resourceId: $refund->id,
                oldValues: ['status' => $current],
                newValues: ['status' => $next],
                reason: $reason,
            );
        }

        return $refund->fresh();
    }

    public function refundableRemaining(PaymentTransaction $tx): string
    {
        $paid = $this->payments->normalizeMoney($tx->getAttributes()['amount'] ?? $tx->amount);
        $already = $this->payments->normalizeMoney($tx->getAttributes()['refunded_amount'] ?? $tx->refunded_amount ?? '0');
        $remaining = bcsub($paid, $already, 2);

        return bccomp($remaining, '0.00', 2) < 0 ? '0.00' : $remaining;
    }

    protected function generateReference(): string
    {
        do {
            $reference = 'RFD-'.strtoupper(Str::random(12));
        } while (PaymentRefund::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
