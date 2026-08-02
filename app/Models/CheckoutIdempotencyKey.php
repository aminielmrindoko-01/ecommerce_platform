<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Single-use checkout token stored for atomic consumption.
 */
class CheckoutIdempotencyKey extends Model
{
    protected $fillable = [
        'token',
        'user_id',
        'order_id',
        'consumed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consumed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }
}
