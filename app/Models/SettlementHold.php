<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Explicit freeze on vendor payable funds (returns, disputes, chargebacks, etc.).
 */
class SettlementHold extends Model
{
    public const STATUSES = ['active', 'released', 'consumed'];

    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'held_at' => 'datetime',
            'releases_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function isActive(): bool
    {
        return ($this->status ?? '') === 'active';
    }
}
