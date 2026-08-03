<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\PaymentReconciliation;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Authorization\AuditLogger;

/**
 * Detects local vs provider payment state mismatches without auto-mutating money.
 */
class PaymentReconciliationService
{
    public function __construct(
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>|null  $context
     */
    public function flagMismatch(
        ?PaymentTransaction $tx,
        ?Order $order,
        string $localStatus,
        string $providerStatus,
        string $detail,
        string $severity = 'high',
        ?User $actor = null,
        ?array $context = null,
    ): PaymentReconciliation {
        $row = new PaymentReconciliation;
        $row->forceFill([
            'payment_transaction_id' => $tx?->id,
            'order_id' => $order?->id ?? $tx?->order_id,
            'provider' => $tx?->provider,
            'local_status' => $localStatus,
            'provider_status' => $providerStatus,
            'severity' => $severity,
            'status' => PaymentReconciliation::STATUS_OPEN,
            'detail' => $detail,
            'context' => $context,
        ])->save();

        $this->audit->security('PAYMENT_RECONCILIATION_REQUIRED', $actor, $severity, [
            'reconciliation_id' => $row->id,
            'payment_transaction_id' => $tx?->id,
            'order_id' => $order?->id ?? $tx?->order_id,
            'local_status' => $localStatus,
            'provider_status' => $providerStatus,
            'detail' => $detail,
        ]);

        $this->audit->log(
            action: 'PAYMENT_RECONCILIATION_REQUIRED',
            actor: $actor,
            resourceType: 'payment_reconciliation',
            resourceId: $row->id,
            newValues: [
                'local_status' => $localStatus,
                'provider_status' => $providerStatus,
                'payment_transaction_id' => $tx?->id,
            ],
            category: 'security',
        );

        return $row->fresh();
    }
}
