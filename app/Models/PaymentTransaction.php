<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Order-level payment transaction (foundation: manual/stub providers only).
 *
 * Sensitive financial fields are assigned by PaymentService, not form requests.
 */
class PaymentTransaction extends Model
{
    public const STATUSES = [
        'pending',
        'processing',
        'paid',
        'failed',
        'cancelled',
        'refunded',
        'partially_refunded',
    ];

    public const PROVIDERS = [
        'manual',
        'stub',
    ];

    /**
     * Only non-sensitive display/metadata fields may be mass-assigned.
     * order_id, amount, status, reference, provider_reference are service-assigned.
     *
     * @var list<string>
     */
    protected $fillable = [
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(PaymentStatusHistory::class);
    }

    public static function isValidStatus(string $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }
}
