<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable debit/credit line within a ledger transaction.
 */
class LedgerEntry extends Model
{
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'ledger_transaction_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
