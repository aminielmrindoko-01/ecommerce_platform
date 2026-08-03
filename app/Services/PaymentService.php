<?php

namespace App\Services;

use App\Events\PaymentCancelled;
use App\Events\PaymentFailed;
use App\Events\PaymentSuccessful;
use App\Models\Order;
use App\Models\PaymentStatusHistory;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Authorization\AuditLogger;
use App\Services\Payments\OrderInventorySettlement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Central authority for order payment state.
 *
 * Gateways must never mark payments paid themselves — only this service
 * (after verification / trusted admin action) mutates payment status.
 */
class PaymentService
{
    /**
     * Canonical statuses (SUCCEEDED mapped as `paid` for compatibility).
     *
     * @var array<string, list<string>>
     */
    protected array $transitions = [
        'pending' => ['initiated', 'processing', 'failed', 'cancelled', 'expired'],
        'initiated' => ['processing', 'failed', 'cancelled', 'expired'],
        'processing' => ['paid', 'failed', 'cancelled', 'expired'],
        'paid' => ['partially_refunded', 'refunded'],
        'partially_refunded' => ['refunded'],
        'failed' => [],
        'cancelled' => [],
        'expired' => [],
        'refunded' => [],
    ];

    public function __construct(
        protected AuditLogger $audit,
        protected OrderInventorySettlement $inventorySettlement,
    ) {}

    /**
     * Create or reuse an open payment attempt for an order.
     * Supports optional idempotency_key (unique) for safe client retries.
     */
    public function ensurePendingTransaction(
        Order $order,
        string $provider = 'stub',
        ?string $idempotencyKey = null,
    ): PaymentTransaction {
        if (! in_array($provider, PaymentTransaction::PROVIDERS, true)) {
            throw new InvalidArgumentException('Unsupported payment provider.');
        }

        if ($idempotencyKey !== null) {
            $idempotencyKey = trim($idempotencyKey);
            if ($idempotencyKey === '') {
                $idempotencyKey = null;
            }
        }

        return DB::transaction(function () use ($order, $provider, $idempotencyKey) {
            if ($idempotencyKey !== null && Schema::hasColumn('payment_transactions', 'idempotency_key')) {
                $byKey = PaymentTransaction::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($byKey) {
                    if ((int) $byKey->order_id !== (int) $order->id) {
                        throw new InvalidArgumentException('Idempotency key already used for another order.');
                    }

                    return $byKey;
                }
            }

            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $existing = PaymentTransaction::query()
                ->where('order_id', $lockedOrder->id)
                ->whereIn('status', ['pending', 'initiated', 'processing', 'paid'])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($existing) {
                return $existing;
            }

            return $this->insertAttempt($lockedOrder, $provider, $idempotencyKey, 'checkout');
        });
    }

    /**
     * Explicitly create a new payment attempt (e.g. after a failed attempt).
     */
    public function createAttempt(
        Order $order,
        string $provider = 'stub',
        ?string $idempotencyKey = null,
        string $source = 'retry',
    ): PaymentTransaction {
        if (! in_array($provider, PaymentTransaction::PROVIDERS, true)) {
            throw new InvalidArgumentException('Unsupported payment provider.');
        }

        if ($idempotencyKey !== null) {
            $idempotencyKey = trim($idempotencyKey) ?: null;
        }

        return DB::transaction(function () use ($order, $provider, $idempotencyKey, $source) {
            if ($idempotencyKey !== null && Schema::hasColumn('payment_transactions', 'idempotency_key')) {
                $byKey = PaymentTransaction::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();
                if ($byKey) {
                    if ((int) $byKey->order_id !== (int) $order->id) {
                        throw new InvalidArgumentException('Idempotency key already used for another order.');
                    }

                    return $byKey;
                }
            }

            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $open = PaymentTransaction::query()
                ->where('order_id', $lockedOrder->id)
                ->whereIn('status', ['pending', 'initiated', 'processing', 'paid'])
                ->exists();

            if ($open) {
                throw new InvalidArgumentException('An open or successful payment attempt already exists.');
            }

            return $this->insertAttempt($lockedOrder, $provider, $idempotencyKey, $source);
        });
    }

    /**
     * Record gateway initialization without requiring a status hop.
     * Stamps initiated_at; optionally moves pending → initiated when safe.
     */
    public function markInitiated(PaymentTransaction $tx, ?User $actor = null, bool $transitionStatus = false): PaymentTransaction
    {
        if (Schema::hasColumn('payment_transactions', 'initiated_at') && ! $tx->initiated_at) {
            $tx->initiated_at = now();
            $tx->save();
        }

        if (! $transitionStatus) {
            $this->audit->log(
                action: 'PAYMENT_INITIATED',
                actor: $actor,
                resourceType: 'payment_transaction',
                resourceId: $tx->id,
                newValues: [
                    'order_id' => $tx->order_id,
                    'reference' => $tx->reference,
                    'provider' => $tx->provider,
                    'amount' => $this->normalizeMoney($tx->amount),
                    'currency' => $tx->currency,
                ],
            );

            return $tx->fresh();
        }

        $order = $tx->order ?? Order::query()->findOrFail($tx->order_id);
        $current = $tx->status ?: 'pending';

        if (in_array($current, ['initiated', 'processing', 'paid'], true)) {
            return $tx->fresh();
        }

        if ($current !== 'pending') {
            return $tx->fresh();
        }

        return $this->transitionOrderPayment(
            $order,
            'initiated',
            $actor,
            'Payment initiated with provider',
            $tx->provider ?: 'stub',
            $tx->provider_reference
        );
    }

    /**
     * Admin/foundation / verified-provider transition for an order's payment attempt.
     *
     * @throws InvalidArgumentException
     */
    public function transitionOrderPayment(
        Order $order,
        string $nextStatus,
        ?User $actor = null,
        ?string $reason = null,
        string $provider = 'manual',
        ?string $providerReference = null,
        ?string $failureCode = null,
    ): PaymentTransaction {
        $nextStatus = strtolower(trim($nextStatus));

        if (! PaymentTransaction::isValidStatus($nextStatus)) {
            throw new InvalidArgumentException('Invalid payment status.');
        }

        if ($providerReference !== null) {
            $providerReference = trim($providerReference);
            if ($providerReference === '') {
                $providerReference = null;
            }
        }

        $reason = $reason !== null ? trim($reason) : null;
        if ($reason === '') {
            $reason = null;
        }

        if ($this->reasonRequired($nextStatus) && blank($reason)) {
            throw new InvalidArgumentException('A reason is required for this payment change.');
        }

        $result = DB::transaction(function () use (
            $order, $nextStatus, $actor, $reason, $provider, $providerReference, $failureCode
        ) {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($providerReference !== null) {
                $byProviderRef = PaymentTransaction::query()
                    ->where('provider_reference', $providerReference)
                    ->lockForUpdate()
                    ->first();

                if ($byProviderRef) {
                    if ((int) $byProviderRef->order_id !== (int) $lockedOrder->id) {
                        throw new InvalidArgumentException('Provider reference already used for another order.');
                    }

                    if ($byProviderRef->status === $nextStatus
                        || ($byProviderRef->status === 'paid' && $nextStatus === 'paid')) {
                        return ['tx' => $byProviderRef, 'changed' => false, 'event' => null];
                    }
                }
            }

            $tx = PaymentTransaction::query()
                ->where('order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $tx) {
                if (! in_array($provider, PaymentTransaction::PROVIDERS, true)) {
                    throw new InvalidArgumentException('Unsupported payment provider.');
                }

                $tx = $this->insertAttempt($lockedOrder, $provider, null, 'admin');
                $tx = PaymentTransaction::query()->whereKey($tx->id)->lockForUpdate()->firstOrFail();
            }

            $current = $tx->status ?: 'pending';

            if ($current === 'paid' && $nextStatus === 'paid') {
                $storedRef = $tx->provider_reference;

                if ($providerReference !== null) {
                    if ($storedRef === null || ! hash_equals((string) $storedRef, $providerReference)) {
                        throw new InvalidArgumentException(
                            'Conflicting provider reference for an already-paid order.'
                        );
                    }
                }

                // Idempotent success: ensure inventory committed once.
                $this->inventorySettlement->commitForPaidOrder($lockedOrder, $actor);

                return ['tx' => $tx, 'changed' => false, 'event' => null];
            }

            if ($current === $nextStatus) {
                return ['tx' => $tx, 'changed' => false, 'event' => null];
            }

            $allowed = $this->transitions[$current] ?? [];
            if (! in_array($nextStatus, $allowed, true)) {
                throw new InvalidArgumentException(
                    "Cannot transition payment from {$current} to {$nextStatus}."
                );
            }

            $this->assertAmountMatchesOrder($tx, $lockedOrder);

            $currency = $lockedOrder->currency ?: config('payments.currency', 'TZS');
            if ($tx->currency !== $currency) {
                throw new InvalidArgumentException('Currency mismatch.');
            }

            if ($providerReference !== null) {
                $conflict = PaymentTransaction::query()
                    ->where('provider_reference', $providerReference)
                    ->where('id', '!=', $tx->id)
                    ->exists();

                if ($conflict) {
                    throw new InvalidArgumentException('Provider reference already used.');
                }

                $tx->provider_reference = $providerReference;
            }

            if (in_array($provider, PaymentTransaction::PROVIDERS, true)) {
                $tx->provider = $provider;
            }

            $tx->status = $nextStatus;

            if ($nextStatus === 'initiated' && Schema::hasColumn('payment_transactions', 'initiated_at')) {
                $tx->initiated_at = $tx->initiated_at ?: now();
            }

            if ($nextStatus === 'paid') {
                $tx->paid_at = now();
                if (Schema::hasColumn('payment_transactions', 'completed_at')) {
                    $tx->completed_at = now();
                }
            }

            if (in_array($nextStatus, ['failed', 'cancelled', 'expired'], true)) {
                if (Schema::hasColumn('payment_transactions', 'completed_at')) {
                    $tx->completed_at = now();
                }
                if ($failureCode && Schema::hasColumn('payment_transactions', 'failure_code')) {
                    $tx->failure_code = Str::limit($failureCode, 64, '');
                }
                if ($reason && Schema::hasColumn('payment_transactions', 'failure_reason')) {
                    $tx->failure_reason = Str::limit($reason, 500, '');
                }
            }

            $tx->save();

            $lockedOrder->payment_status = $nextStatus === 'initiated' ? 'pending' : $nextStatus;
            // Soft-sync marketplace order lifecycle when first paid.
            if ($nextStatus === 'paid' && in_array(($lockedOrder->status ?: 'pending'), ['pending', 'paid'], true)) {
                $lockedOrder->status = 'confirmed';
            }
            $lockedOrder->save();

            PaymentStatusHistory::create([
                'payment_transaction_id' => $tx->id,
                'actor_user_id' => $actor?->id,
                'from_status' => $current,
                'to_status' => $nextStatus,
                'reason' => $reason,
                'metadata' => [
                    'provider' => $tx->provider,
                    'reference' => $tx->reference,
                    'provider_reference' => $tx->provider_reference,
                    'amount' => $this->normalizeMoney($tx->amount),
                    'currency' => $tx->currency,
                ],
            ]);

            if ($nextStatus === 'paid') {
                $this->inventorySettlement->commitForPaidOrder($lockedOrder, $actor);
            }

            if (in_array($nextStatus, ['failed', 'cancelled', 'expired'], true)) {
                $this->inventorySettlement->releaseForUnpaidOrder(
                    $lockedOrder,
                    $actor,
                    "Payment {$nextStatus} — reservation released"
                );
            }

            $auditAction = match ($nextStatus) {
                'initiated' => 'PAYMENT_INITIATED',
                'processing' => 'PAYMENT_PROCESSING',
                'paid' => 'PAYMENT_SUCCEEDED',
                'failed' => 'PAYMENT_FAILED',
                'cancelled' => 'PAYMENT_CANCELLED',
                'expired' => 'PAYMENT_EXPIRED',
                'refunded' => 'PAYMENT_REFUNDED',
                'partially_refunded' => 'PAYMENT_PARTIALLY_REFUNDED',
                default => 'PAYMENT_STATUS_CHANGED',
            };

            $this->audit->log(
                action: $auditAction,
                actor: $actor,
                resourceType: 'payment_transaction',
                resourceId: $tx->id,
                oldValues: ['status' => $current],
                newValues: [
                    'status' => $nextStatus,
                    'order_id' => $lockedOrder->id,
                    'amount' => $this->normalizeMoney($tx->amount),
                    'currency' => $tx->currency,
                    'provider' => $tx->provider,
                    'reference' => $tx->reference,
                    'provider_reference' => $tx->provider_reference,
                ],
                reason: $reason,
                category: 'business',
            );

            $event = match ($nextStatus) {
                'paid' => 'successful',
                'failed' => 'failed',
                'cancelled' => 'cancelled',
                default => null,
            };

            return ['tx' => $tx->fresh(['order']), 'changed' => true, 'event' => $event];
        });

        if ($result['changed'] && $result['event']) {
            $tx = $result['tx'];
            match ($result['event']) {
                'successful' => PaymentSuccessful::dispatch($tx),
                'failed' => PaymentFailed::dispatch($tx),
                'cancelled' => PaymentCancelled::dispatch($tx),
                default => null,
            };
        }

        return $result['tx'];
    }

    /**
     * Process a verified provider callback-style update (idempotent).
     */
    public function processProviderResult(
        Order $order,
        string $nextStatus,
        string $provider,
        string $providerReference,
        ?User $actor = null,
        ?string $reason = null,
    ): PaymentTransaction {
        return $this->transitionOrderPayment(
            $order,
            $nextStatus,
            $actor,
            $reason,
            $provider,
            $providerReference
        );
    }

    /**
     * @return list<string>
     */
    public function allowedTransitions(PaymentTransaction|string $current): array
    {
        $status = $current instanceof PaymentTransaction
            ? ($current->status ?: 'pending')
            : $current;

        return $this->transitions[$status] ?? [];
    }

    public function reasonRequired(string $toStatus): bool
    {
        return in_array($toStatus, ['failed', 'cancelled', 'expired'], true);
    }

    public function authoritativeAmount(Order $order): string
    {
        $raw = $order->getAttributes()['total_price'] ?? $order->total_price;

        return $this->normalizeMoney($raw);
    }

    public function normalizeMoney(mixed $value): string
    {
        if ($value === null || $value === '') {
            throw new InvalidArgumentException('Money value is required.');
        }

        $string = is_string($value) ? trim($value) : (string) $value;

        if (! preg_match('/^-?\d+(\.\d+)?$/', $string)) {
            throw new InvalidArgumentException('Invalid money value.');
        }

        return bcadd($string, '0', 2);
    }

    protected function insertAttempt(
        Order $lockedOrder,
        string $provider,
        ?string $idempotencyKey,
        string $source,
    ): PaymentTransaction {
        $attempt = 1;
        if (Schema::hasColumn('payment_transactions', 'attempt_number')) {
            $attempt = (int) PaymentTransaction::query()
                ->where('order_id', $lockedOrder->id)
                ->max('attempt_number') + 1;
        }

        $amount = $this->authoritativeAmount($lockedOrder);
        $currency = $lockedOrder->currency ?: config('payments.currency', 'TZS');

        // Retry after failed/expired payment: re-reserve stock if previously released.
        $invState = $lockedOrder->inventory_state ?? null;
        if (in_array($invState, [OrderInventorySettlement::STATE_RELEASED, OrderInventorySettlement::STATE_NONE], true)
            && $attempt > 1) {
            $this->rereserveOrderItems($lockedOrder);
        }

        $tx = new PaymentTransaction;
        $tx->order_id = $lockedOrder->id;
        $tx->reference = $this->generateReference();
        $tx->provider = $provider;
        $tx->amount = $amount;
        $tx->currency = $currency;
        $tx->status = 'pending';
        $tx->provider_reference = null;
        $tx->metadata = [
            'payment_method' => $lockedOrder->payment_method,
            'source' => $source,
        ];

        if (Schema::hasColumn('payment_transactions', 'attempt_number')) {
            $tx->attempt_number = max(1, $attempt);
        }
        if ($idempotencyKey && Schema::hasColumn('payment_transactions', 'idempotency_key')) {
            $tx->idempotency_key = $idempotencyKey;
        }
        if (Schema::hasColumn('payment_transactions', 'refunded_amount')) {
            $tx->refunded_amount = '0.00';
        }

        $tx->save();

        if (! in_array(($lockedOrder->payment_status ?: 'pending'), ['pending', 'initiated'], true)) {
            // Keep failed/cancelled history on order until a new attempt progresses.
            if (! in_array($lockedOrder->payment_status, ['paid', 'partially_refunded', 'refunded'], true)) {
                $lockedOrder->payment_status = 'pending';
                $lockedOrder->save();
            }
        }

        $this->audit->log(
            action: 'PAYMENT_ATTEMPT_CREATED',
            actor: null,
            resourceType: 'payment_transaction',
            resourceId: $tx->id,
            newValues: [
                'order_id' => $lockedOrder->id,
                'attempt_number' => $tx->attempt_number ?? 1,
                'amount' => $amount,
                'currency' => $currency,
                'provider' => $provider,
                'reference' => $tx->reference,
            ],
        );

        return $tx->fresh();
    }

    /**
     * Re-reserve line items when starting a new payment attempt after release.
     */
    protected function rereserveOrderItems(Order $order): void
    {
        $order->loadMissing('items.product');
        $actor = null;
        foreach ($order->items as $item) {
            if (! $item->product) {
                continue;
            }
            $qty = (int) $item->quantity;
            if ($qty < 1) {
                continue;
            }
            app(\App\Services\Catalog\InventoryService::class)->reserve(
                $item->product->fresh(),
                $qty,
                $actor,
                (string) $order->id,
            );
        }
        $this->inventorySettlement->markReserved($order->fresh());
    }

    protected function assertAmountMatchesOrder(PaymentTransaction $tx, Order $order): void
    {
        $expected = $this->authoritativeAmount($order);
        $actual = $this->normalizeMoney($tx->getAttributes()['amount'] ?? $tx->amount);

        if (bccomp($actual, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        if (bccomp($actual, $expected, 2) !== 0) {
            throw new InvalidArgumentException('Payment amount does not match order total.');
        }
    }

    protected function generateReference(): string
    {
        do {
            $reference = 'PAY-'.strtoupper(Str::random(12));
        } while (PaymentTransaction::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
