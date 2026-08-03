<?php

namespace App\Services\Finance;

use App\Contracts\PayoutGatewayInterface;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayout;
use App\Services\Authorization\AuditLogger;
use App\Services\PaymentService;
use App\Support\Finance\StubPayoutGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Vendor payout lifecycle with ledger settlement and sandbox provider.
 *
 * PAYOUT INTEGRATION STATUS: SANDBOX / NOT PRODUCTION-READY
 */
class PayoutService
{
    /**
     * @var array<string, list<string>>
     */
    protected array $transitions = [
        'pending' => ['approved', 'cancelled', 'rejected'],
        'approved' => ['processing', 'cancelled', 'rejected'],
        'processing' => ['completed', 'failed'],
        'completed' => [],
        'failed' => [],
        'cancelled' => [],
        'rejected' => [],
    ];

    public function __construct(
        protected VendorPayableService $payables,
        protected LedgerService $ledger,
        protected PaymentService $payments,
        protected AuditLogger $audit,
        protected PayoutGatewayInterface $gateway,
    ) {}

    /**
     * Vendor requests a payout of their own available funds.
     */
    public function request(
        Vendor $vendor,
        string $amount,
        User $actor,
        ?string $idempotencyKey = null,
        ?string $destinationToken = null,
    ): VendorPayout {
        if ((int) ($actor->vendor?->id) !== (int) $vendor->id
            && ! $actor->hasPermission('payouts.process')
            && ! $actor->hasPermission('payouts.approve')) {
            throw new InvalidArgumentException('You cannot request a payout for this vendor.');
        }

        if (! $vendor->canSell()) {
            throw new InvalidArgumentException('Vendor is not approved for payouts.');
        }

        $amount = $this->payments->normalizeMoney($amount);
        if (bccomp($amount, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('Payout amount must be greater than zero.');
        }

        $currency = config('finance.currency', 'TZS');
        $idempotencyKey = $idempotencyKey !== null ? trim($idempotencyKey) : null;
        if ($idempotencyKey === '') {
            $idempotencyKey = null;
        }

        return DB::transaction(function () use ($vendor, $amount, $actor, $idempotencyKey, $destinationToken, $currency) {
            if ($idempotencyKey) {
                $existing = VendorPayout::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    if ((int) $existing->vendor_id !== (int) $vendor->id) {
                        throw new InvalidArgumentException('Idempotency key already used for another vendor.');
                    }

                    return $existing;
                }
            }

            // Lock vendor row to serialize concurrent payout requests.
            Vendor::query()->whereKey($vendor->id)->lockForUpdate()->firstOrFail();

            $available = $this->payables->availableBalance($vendor->fresh());
            if (bccomp($amount, $available, 2) > 0) {
                throw new InvalidArgumentException(
                    "Insufficient payable balance. Available: {$available} {$currency}."
                );
            }

            $payout = new VendorPayout;
            $payout->forceFill([
                'reference' => $this->generateReference(),
                'idempotency_key' => $idempotencyKey,
                'vendor_id' => $vendor->id,
                'requested_by' => $actor->id,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'pending',
                'provider' => $this->gateway->key(),
                'destination_token' => $destinationToken ? Str::limit($destinationToken, 128, '') : null,
                'requested_at' => now(),
                'metadata' => ['sandbox' => ! $this->gateway->supportsLivePayouts()],
            ])->save();

            $this->audit->log(
                action: 'PAYOUT_REQUESTED',
                actor: $actor,
                resourceType: 'vendor_payout',
                resourceId: $payout->id,
                newValues: [
                    'vendor_id' => $vendor->id,
                    'amount' => $amount,
                    'currency' => $currency,
                    'reference' => $payout->reference,
                ],
            );

            return $payout->fresh();
        });
    }

    public function approve(VendorPayout $payout, User $actor, ?string $reason = null): VendorPayout
    {
        if (! $actor->hasPermission('payouts.approve') && ! $actor->hasPermission('payouts.process')) {
            // process may approve in small teams when approve perm not assigned separately
            throw new InvalidArgumentException('Missing payout approval permission.');
        }

        return $this->transition($payout, 'approved', $actor, $reason, function (VendorPayout $locked) use ($actor) {
            $locked->approved_by = $actor->id;
            $locked->approved_at = now();
        }, 'PAYOUT_APPROVED');
    }

    public function reject(VendorPayout $payout, User $actor, string $reason): VendorPayout
    {
        if (! $actor->hasPermission('payouts.approve') && ! $actor->hasPermission('payouts.process')) {
            throw new InvalidArgumentException('Missing payout rejection permission.');
        }

        return $this->transition($payout, 'rejected', $actor, $reason, null, 'PAYOUT_REJECTED');
    }

    public function process(VendorPayout $payout, User $actor): VendorPayout
    {
        if (! $actor->hasPermission('payouts.process')) {
            throw new InvalidArgumentException('Missing payout process permission.');
        }

        if (config('finance.payout_separation_of_duties', true)
            && $payout->approved_by
            && (int) $payout->approved_by === (int) $actor->id
            && ! $actor->isSuperAdmin()) {
            throw new InvalidArgumentException(
                'Separation of duties: the approver cannot process the same payout.'
            );
        }

        $payout = $this->transition($payout, 'processing', $actor, null, function (VendorPayout $locked) use ($actor) {
            $locked->processed_by = $actor->id;
            $locked->processed_at = now();
        }, 'PAYOUT_PROCESSING');

        $result = $this->gateway->initiate($payout->fresh());

        return DB::transaction(function () use ($payout, $actor, $result) {
            /** @var VendorPayout $locked */
            $locked = VendorPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if (($result['provider_reference'] ?? null)) {
                $conflict = VendorPayout::query()
                    ->where('provider_reference', $result['provider_reference'])
                    ->where('id', '!=', $locked->id)
                    ->exists();
                if ($conflict) {
                    throw new InvalidArgumentException('Provider payout reference already used.');
                }
                $locked->provider_reference = $result['provider_reference'];
            }

            $meta = $locked->metadata ?? [];
            $meta['provider_init'] = [
                'status' => $result['status'] ?? null,
                'message' => $result['message'] ?? null,
            ];
            $locked->metadata = $meta;
            $locked->save();

            // Sandbox stub: auto-complete after initiate (no live money movement).
            if (! $this->gateway->supportsLivePayouts() && ($result['status'] ?? '') === 'accepted') {
                return $this->completeSandbox($locked, $actor);
            }

            return $locked->fresh();
        });
    }

    public function markFailed(VendorPayout $payout, User $actor, string $reason, ?string $code = null): VendorPayout
    {
        return $this->transition($payout, 'failed', $actor, $reason, function (VendorPayout $locked) use ($code, $reason) {
            $locked->failure_code = $code ? Str::limit($code, 64, '') : null;
            $locked->failure_reason = Str::limit($reason, 500, '');
        }, 'PAYOUT_FAILED');
    }

    protected function completeSandbox(VendorPayout $payout, User $actor): VendorPayout
    {
        return DB::transaction(function () use ($payout, $actor) {
            /** @var VendorPayout $locked */
            $locked = VendorPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'completed') {
                return $locked;
            }
            if ($locked->status !== 'processing') {
                throw new InvalidArgumentException('Payout must be processing to complete.');
            }

            Vendor::query()->whereKey($locked->vendor_id)->lockForUpdate()->firstOrFail();

            $amount = $this->payments->normalizeMoney($locked->amount);
            $txn = $this->ledger->post([
                'type' => 'payout',
                'currency' => $locked->currency,
                'description' => 'Vendor payout '.$locked->reference,
                'vendor_id' => $locked->vendor_id,
                'actor' => $actor,
                'idempotency_key' => 'payout-complete:'.$locked->id,
                'metadata' => ['payout_id' => $locked->id],
            ], [
                [
                    'account' => 'VENDOR_PAYABLE',
                    'debit' => $amount,
                    'credit' => '0.00',
                    'vendor_id' => $locked->vendor_id,
                ],
                [
                    'account' => 'PLATFORM_CASH',
                    'debit' => '0.00',
                    'credit' => $amount,
                ],
            ]);

            $locked->ledger_transaction_id = $txn->id;
            $locked->status = 'completed';
            $locked->completed_at = now();
            $locked->save();

            $this->audit->log(
                action: 'PAYOUT_COMPLETED',
                actor: $actor,
                resourceType: 'vendor_payout',
                resourceId: $locked->id,
                newValues: [
                    'amount' => $amount,
                    'currency' => $locked->currency,
                    'vendor_id' => $locked->vendor_id,
                    'provider_reference' => $locked->provider_reference,
                ],
            );

            return $locked->fresh();
        });
    }

    /**
     * @param  callable(VendorPayout):void|null  $mutator
     */
    protected function transition(
        VendorPayout $payout,
        string $next,
        User $actor,
        ?string $reason,
        ?callable $mutator,
        string $auditAction,
    ): VendorPayout {
        $next = strtolower(trim($next));
        if (! VendorPayout::isValidStatus($next)) {
            throw new InvalidArgumentException('Invalid payout status.');
        }

        return DB::transaction(function () use ($payout, $next, $actor, $reason, $mutator, $auditAction) {
            /** @var VendorPayout $locked */
            $locked = VendorPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $current = $locked->status ?: 'pending';

            if ($current === $next) {
                return $locked;
            }

            if (! in_array($next, $this->transitions[$current] ?? [], true)) {
                throw new InvalidArgumentException("Cannot transition payout from {$current} to {$next}.");
            }

            if ($mutator) {
                $mutator($locked);
            }

            $locked->status = $next;
            $locked->save();

            $this->audit->log(
                action: $auditAction,
                actor: $actor,
                resourceType: 'vendor_payout',
                resourceId: $locked->id,
                oldValues: ['status' => $current],
                newValues: ['status' => $next],
                reason: $reason,
            );

            return $locked->fresh();
        });
    }

    protected function generateReference(): string
    {
        do {
            $reference = 'PO-'.strtoupper(Str::random(12));
        } while (VendorPayout::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
