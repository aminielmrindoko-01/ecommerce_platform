<?php

namespace App\Services\Operations;

use App\Models\User;
use App\Models\Vendor;
use App\Services\Authorization\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VendorFinancialStatusService
{
    public const STATUSES = ['active', 'payout_hold', 'financial_review', 'suspended'];

    public function __construct(
        protected AuditLogger $audit,
    ) {}

    public function setStatus(Vendor $vendor, string $status, User $actor, ?string $reason = null): Vendor
    {
        if (! $actor->hasPermission('vendors.suspend') && ! $actor->hasPermission('commission.manage')
            && ! $actor->hasPermission('payouts.process')) {
            throw new InvalidArgumentException('Missing permission to change vendor financial status.');
        }

        if (! in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Invalid financial status.');
        }

        return DB::transaction(function () use ($vendor, $status, $actor, $reason) {
            /** @var Vendor $locked */
            $locked = Vendor::query()->whereKey($vendor->id)->lockForUpdate()->firstOrFail();
            $from = $locked->financialStatus();
            if ($from === $status) {
                return $locked;
            }

            $locked->forceFill(['financial_status' => $status])->save();

            $this->audit->log(
                action: $status === 'active' ? 'VENDOR_PAYOUT_ELIGIBLE' : 'VENDOR_PAYOUT_HOLD',
                actor: $actor,
                resourceType: 'vendor',
                resourceId: $locked->id,
                oldValues: ['financial_status' => $from],
                newValues: ['financial_status' => $status],
                reason: $reason,
            );

            return $locked->fresh();
        });
    }
}
