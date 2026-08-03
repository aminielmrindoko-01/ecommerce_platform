<?php

namespace App\Services\Finance;

use App\Models\User;
use App\Models\VendorPayout;
use App\Services\Authorization\AuditLogger;

/**
 * Detects payout provider vs local state mismatches without rewriting history.
 */
class PayoutReconciliationService
{
    public function __construct(
        protected AuditLogger $audit,
        protected \App\Contracts\PayoutGatewayInterface $gateway,
        protected \App\Services\Payments\PaymentReconciliationService $paymentReconcile,
    ) {}

    public function check(VendorPayout $payout, ?User $actor = null): ?\App\Models\PaymentReconciliation
    {
        $remote = $this->gateway->verify($payout);
        $local = $payout->status;
        $remoteStatus = strtolower((string) ($remote['status'] ?? ''));

        $mismatch = false;
        $detail = null;

        if ($local === 'processing' && $remoteStatus === 'completed') {
            $mismatch = true;
            $detail = 'Provider reports payout completed but local status is processing.';
        } elseif ($local === 'completed' && in_array($remoteStatus, ['failed', 'cancelled'], true)) {
            $mismatch = true;
            $detail = 'Local payout completed but provider reports '.$remoteStatus.'.';
        }

        if (! $mismatch) {
            return null;
        }

        $this->audit->log(
            action: 'PAYOUT_RECONCILIATION_REQUIRED',
            actor: $actor,
            resourceType: 'vendor_payout',
            resourceId: $payout->id,
            newValues: [
                'local_status' => $local,
                'provider_status' => $remoteStatus,
            ],
            category: 'security',
        );

        return $this->paymentReconcile->flagMismatch(
            null,
            null,
            $local,
            $remoteStatus,
            $detail,
            'high',
            $actor,
            [
                'payout_id' => $payout->id,
                'vendor_id' => $payout->vendor_id,
                'provider_reference' => $payout->provider_reference,
            ],
        );
    }
}
