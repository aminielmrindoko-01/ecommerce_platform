<?php

namespace App\Services\Operations;

use App\Models\SettlementHold;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Authorization\AuditLogger;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Explicit settlement holds — authoritative freeze on vendor payable slices.
 */
class SettlementHoldService
{
    public function __construct(
        protected PaymentService $payments,
        protected AuditLogger $audit,
    ) {}

    public function create(
        Vendor $vendor,
        string $amount,
        string $reasonCode,
        ?User $actor = null,
        ?int $orderId = null,
        ?int $orderItemId = null,
        ?string $sourceType = null,
        ?string $sourceId = null,
        ?string $reason = null,
        ?\DateTimeInterface $releasesAt = null,
    ): SettlementHold {
        $amount = $this->payments->normalizeMoney($amount);
        if (bccomp($amount, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('Hold amount must be greater than zero.');
        }

        $allowed = ['settlement_period', 'return', 'dispute', 'chargeback', 'manual'];
        if (! in_array($reasonCode, $allowed, true)) {
            throw new InvalidArgumentException('Invalid hold reason code.');
        }

        return DB::transaction(function () use (
            $vendor, $amount, $reasonCode, $actor, $orderId, $orderItemId,
            $sourceType, $sourceId, $reason, $releasesAt
        ) {
            if ($sourceType && $sourceId) {
                $existing = SettlementHold::query()
                    ->where('source_type', $sourceType)
                    ->where('source_id', $sourceId)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    return $existing;
                }
            }

            $hold = new SettlementHold;
            $hold->forceFill([
                'reference' => 'HLD-'.strtoupper(Str::random(10)),
                'vendor_id' => $vendor->id,
                'order_id' => $orderId,
                'order_item_id' => $orderItemId,
                'reason_code' => $reasonCode,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'amount' => $amount,
                'currency' => config('finance.currency', 'TZS'),
                'status' => 'active',
                'reason' => $reason,
                'held_at' => now(),
                'releases_at' => $releasesAt,
                'created_by' => $actor?->id,
            ])->save();

            $this->audit->log(
                action: 'SETTLEMENT_HOLD_CREATED',
                actor: $actor,
                resourceType: 'settlement_hold',
                resourceId: $hold->id,
                newValues: [
                    'vendor_id' => $vendor->id,
                    'amount' => $amount,
                    'currency' => $hold->currency,
                    'reason_code' => $reasonCode,
                    'order_id' => $orderId,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                ],
            );

            return $hold->fresh();
        });
    }

    public function release(SettlementHold $hold, User $actor, ?string $reason = null): SettlementHold
    {
        return DB::transaction(function () use ($hold, $actor, $reason) {
            /** @var SettlementHold $locked */
            $locked = SettlementHold::query()->whereKey($hold->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'active') {
                return $locked;
            }

            $locked->forceFill([
                'status' => 'released',
                'released_at' => now(),
                'released_by' => $actor->id,
                'reason' => $reason ?: $locked->reason,
            ])->save();

            $this->audit->log(
                action: 'SETTLEMENT_HOLD_RELEASED',
                actor: $actor,
                resourceType: 'settlement_hold',
                resourceId: $locked->id,
                oldValues: ['status' => 'active'],
                newValues: [
                    'status' => 'released',
                    'vendor_id' => $locked->vendor_id,
                    'amount' => (string) $locked->getAttributes()['amount'],
                    'currency' => $locked->currency,
                ],
            );

            return $locked->fresh();
        });
    }

    public function activeHeldAmount(int $vendorId): string
    {
        $sum = SettlementHold::query()
            ->where('vendor_id', $vendorId)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('releases_at')->orWhere('releases_at', '>', now());
            })
            ->sum('amount');

        return $this->payments->normalizeMoney($sum ?: '0');
    }

    /**
     * Hard-block payouts for high-risk holds (disputes/chargebacks/manual freezes).
     * Return holds reduce available balance instead of hard-blocking the vendor.
     */
    public function vendorHasBlockingHold(int $vendorId): bool
    {
        return SettlementHold::query()
            ->where('vendor_id', $vendorId)
            ->where('status', 'active')
            ->whereIn('reason_code', ['dispute', 'chargeback', 'manual'])
            ->exists();
    }
}
