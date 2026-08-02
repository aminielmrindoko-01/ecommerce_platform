<?php

namespace App\Services;

use App\Events\PaymentCancelled;
use App\Events\PaymentFailed;
use App\Events\PaymentSuccessful;
use App\Models\Order;
use App\Models\PaymentStatusHistory;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Central authority for order payment state (foundation: manual/stub only).
 *
 * Uses lockForUpdate, decimal-safe comparisons, and unique references for idempotency.
 */
class PaymentService
{
    /**
     * @var array<string, list<string>>
     */
    protected array $transitions = [
        'pending' => ['processing', 'failed', 'cancelled'],
        'processing' => ['paid', 'failed', 'cancelled'],
        'paid' => ['partially_refunded', 'refunded'],
        'partially_refunded' => ['refunded'],
        'failed' => [],
        'cancelled' => [],
        'refunded' => [],
    ];

    /**
     * Create or reuse a pending stub/manual transaction for an order at checkout.
     */
    public function ensurePendingTransaction(Order $order, string $provider = 'stub'): PaymentTransaction
    {
        if (! in_array($provider, PaymentTransaction::PROVIDERS, true)) {
            throw new InvalidArgumentException('Unsupported payment provider.');
        }

        return DB::transaction(function () use ($order, $provider) {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $existing = PaymentTransaction::query()
                ->where('order_id', $lockedOrder->id)
                ->whereIn('status', ['pending', 'processing', 'paid'])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($existing) {
                return $existing;
            }

            $amount = $this->authoritativeAmount($lockedOrder);

            $tx = new PaymentTransaction;
            $tx->order_id = $lockedOrder->id;
            $tx->reference = $this->generateReference();
            $tx->provider = $provider;
            $tx->amount = $amount;
            $tx->currency = 'TZS';
            $tx->status = 'pending';
            $tx->provider_reference = null;
            $tx->metadata = [
                'payment_method' => $lockedOrder->payment_method,
                'source' => 'checkout',
            ];
            $tx->save();

            if (($lockedOrder->payment_status ?: 'pending') !== 'pending') {
                $lockedOrder->payment_status = 'pending';
                $lockedOrder->save();
            }

            return $tx->fresh();
        });
    }

    /**
     * Admin/foundation transition for an order's active payment transaction.
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

        $result = DB::transaction(function () use ($order, $nextStatus, $actor, $reason, $provider, $providerReference) {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            // Idempotency: same provider reference already processed → return existing.
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

            // Use the latest transaction for this order (including terminal states).
            // Do not silently create a new pending row to bypass failed/cancelled/refunded.
            $tx = PaymentTransaction::query()
                ->where('order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $tx) {
                if (! in_array($provider, PaymentTransaction::PROVIDERS, true)) {
                    throw new InvalidArgumentException('Unsupported payment provider.');
                }

                $tx = new PaymentTransaction;
                $tx->order_id = $lockedOrder->id;
                $tx->reference = $this->generateReference();
                $tx->provider = $provider;
                $tx->amount = $this->authoritativeAmount($lockedOrder);
                $tx->currency = 'TZS';
                $tx->status = 'pending';
                $tx->metadata = [
                    'payment_method' => $lockedOrder->payment_method,
                    'source' => 'admin',
                ];
                $tx->save();

                $tx = PaymentTransaction::query()->whereKey($tx->id)->lockForUpdate()->firstOrFail();
            }

            $current = $tx->status ?: 'pending';

            // Already paid: same provider reference (or no new ref) → idempotent no-op.
            // Different provider reference → reject (never silent).
            if ($current === 'paid' && $nextStatus === 'paid') {
                $storedRef = $tx->provider_reference;

                if ($providerReference !== null) {
                    if ($storedRef === null || ! hash_equals((string) $storedRef, $providerReference)) {
                        throw new InvalidArgumentException(
                            'Conflicting provider reference for an already-paid order.'
                        );
                    }
                }

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

            if ($tx->currency !== 'TZS') {
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
            if ($nextStatus === 'paid') {
                $tx->paid_at = now();
            }
            $tx->save();

            $lockedOrder->payment_status = $nextStatus;
            // Soft-sync legacy orders.status for admin revenue KPIs when first paid.
            if ($nextStatus === 'paid' && ($lockedOrder->status ?: 'pending') === 'pending') {
                $lockedOrder->status = 'paid';
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

            $event = match ($nextStatus) {
                'paid' => 'successful',
                'failed' => 'failed',
                'cancelled' => 'cancelled',
                default => null,
            };

            return ['tx' => $tx->fresh(['order']), 'changed' => true, 'event' => $event];
        });

        // Events only after successful commit.
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
     * Process a stub/manual callback-style update keyed by provider reference (idempotent).
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
        return in_array($toStatus, ['failed', 'cancelled'], true);
    }

    /**
     * Authoritative order amount as a 2-decimal money string (no float math).
     */
    public function authoritativeAmount(Order $order): string
    {
        $raw = $order->getAttributes()['total_price'] ?? $order->total_price;

        return $this->normalizeMoney($raw);
    }

    /**
     * Normalize a decimal/money value to scale-2 string without binary floats.
     */
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
