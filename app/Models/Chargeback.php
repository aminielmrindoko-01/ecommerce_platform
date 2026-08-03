<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Internal representation of a provider-reported chargeback.
 * NOT the same as a customer refund. Not connected to a live bank API.
 *
 * CHARGEBACK INTEGRATION: INTERNAL ARCHITECTURE / NOT PROVIDER-CONNECTED
 */
class Chargeback extends Model
{
    public const STATUSES = [
        'received',
        'under_review',
        'responded',
        'accepted',
        'lost',
        'won',
        'closed',
    ];

    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
            'received_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function settlementHold(): BelongsTo
    {
        return $this->belongsTo(SettlementHold::class);
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class);
    }

    public static function isValidStatus(string $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }
}
