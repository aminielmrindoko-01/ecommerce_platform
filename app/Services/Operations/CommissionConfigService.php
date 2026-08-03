<?php

namespace App\Services\Operations;

use App\Models\CommissionConfig;
use App\Models\User;
use App\Services\Authorization\AuditLogger;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Resolves commission rules with platform default + optional vendor override.
 * Historical entitlements never re-read live config.
 */
class CommissionConfigService
{
    public function __construct(
        protected PaymentService $payments,
        protected AuditLogger $audit,
    ) {}

    /**
     * @return array{type:string, rate:string, fixed_amount:string, scope:string, scope_id:?int}
     */
    public function resolveForVendor(?int $vendorId = null): array
    {
        if ($vendorId) {
            $vendorCfg = CommissionConfig::query()
                ->where('scope', 'vendor')
                ->where('scope_id', $vendorId)
                ->where('is_active', true)
                ->first();
            if ($vendorCfg) {
                return $this->toOverride($vendorCfg);
            }
        }

        $platform = CommissionConfig::query()
            ->where('scope', 'platform')
            ->whereNull('scope_id')
            ->where('is_active', true)
            ->first();

        if ($platform) {
            return $this->toOverride($platform);
        }

        // Fallback to config/finance.php
        return [
            'type' => (string) config('finance.commission.type', 'percentage'),
            'rate' => (string) config('finance.commission.rate', '0.10'),
            'fixed_amount' => (string) config('finance.commission.fixed_amount', '0.00'),
            'scope' => 'config',
            'scope_id' => null,
        ];
    }

    public function updatePlatform(User $actor, string $type, string $rate, string $fixedAmount = '0.00'): CommissionConfig
    {
        if (! $actor->hasPermission('commission.manage')) {
            throw new InvalidArgumentException('Missing commission.manage permission.');
        }

        return $this->upsert($actor, 'platform', null, $type, $rate, $fixedAmount);
    }

    public function updateVendorOverride(
        User $actor,
        int $vendorId,
        string $type,
        string $rate,
        string $fixedAmount = '0.00',
        bool $active = true,
    ): CommissionConfig {
        if (! $actor->hasPermission('commission.manage')) {
            throw new InvalidArgumentException('Missing commission.manage permission.');
        }

        return $this->upsert($actor, 'vendor', $vendorId, $type, $rate, $fixedAmount, $active);
    }

    protected function upsert(
        User $actor,
        string $scope,
        ?int $scopeId,
        string $type,
        string $rate,
        string $fixedAmount,
        bool $active = true,
    ): CommissionConfig {
        if (! in_array($type, ['percentage', 'fixed'], true)) {
            throw new InvalidArgumentException('Invalid commission type.');
        }

        $rateRaw = trim($rate);
        if (! preg_match('/^\d+(\.\d+)?$/', $rateRaw)) {
            throw new InvalidArgumentException('Invalid commission rate.');
        }
        $rateNorm = bcadd($rateRaw, '0', 4);
        if ($type === 'percentage' && (bccomp($rateNorm, '0.0000', 4) < 0 || bccomp($rateNorm, '1.0000', 4) > 0)) {
            throw new InvalidArgumentException('Commission rate must be between 0 and 1.');
        }

        $fixed = $this->payments->normalizeMoney($fixedAmount);

        return DB::transaction(function () use ($actor, $scope, $scopeId, $type, $rateNorm, $fixed, $active) {
            $cfg = CommissionConfig::query()
                ->where('scope', $scope)
                ->where(function ($q) use ($scopeId) {
                    $scopeId === null ? $q->whereNull('scope_id') : $q->where('scope_id', $scopeId);
                })
                ->lockForUpdate()
                ->first();

            $old = $cfg ? [
                'type' => $cfg->type,
                'rate' => (string) $cfg->getAttributes()['rate'],
                'fixed_amount' => (string) $cfg->getAttributes()['fixed_amount'],
                'is_active' => (bool) $cfg->is_active,
            ] : null;

            if (! $cfg) {
                $cfg = new CommissionConfig;
            }

            $cfg->forceFill([
                'scope' => $scope,
                'scope_id' => $scopeId,
                'type' => $type,
                'rate' => $rateNorm,
                'fixed_amount' => $fixed,
                'is_active' => $active,
                'updated_by' => $actor->id,
            ])->save();

            $this->audit->log(
                action: 'COMMISSION_CONFIG_UPDATED',
                actor: $actor,
                resourceType: 'commission_config',
                resourceId: $cfg->id,
                oldValues: $old,
                newValues: [
                    'scope' => $scope,
                    'scope_id' => $scopeId,
                    'type' => $type,
                    'rate' => $rateNorm,
                    'fixed_amount' => $fixed,
                    'is_active' => $active,
                ],
            );

            return $cfg->fresh();
        });
    }

    /**
     * @return array{type:string, rate:string, fixed_amount:string, scope:string, scope_id:?int}
     */
    protected function toOverride(CommissionConfig $cfg): array
    {
        return [
            'type' => (string) $cfg->type,
            'rate' => (string) $cfg->getAttributes()['rate'],
            'fixed_amount' => (string) $cfg->getAttributes()['fixed_amount'],
            'scope' => (string) $cfg->scope,
            'scope_id' => $cfg->scope_id ? (int) $cfg->scope_id : null,
        ];
    }
}
