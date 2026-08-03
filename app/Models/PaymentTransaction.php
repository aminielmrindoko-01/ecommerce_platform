<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Order-level payment attempt / transaction.
 *
 * Sensitive financial fields are assigned by PaymentService, not form requests.
 * Canonical success status remains `paid` (SUCCEEDED in architecture docs).
 */
class PaymentTransaction extends Model
{
    public const STATUSES = [
        'pending',
        'initiated',
        'processing',
        'paid', // SUCCEEDED
        'failed',
        'cancelled',
        'expired',
        'refunded',
        'partially_refunded',
    ];

    public const PROVIDERS = [
        'manual',
        'stub',
        'pesapal',
    ];

    /**
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
            'refunded_amount' => 'decimal:2',
            'metadata' => 'array',
            'paid_at' => 'datetime',
            'initiated_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }

    public static function isValidStatus(string $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }

    public function remainingRefundable(): string
    {
        $paid = bcadd((string) ($this->getAttributes()['amount'] ?? '0'), '0', 2);
        $refunded = bcadd((string) ($this->getAttributes()['refunded_amount'] ?? '0'), '0', 2);
        $remaining = bcsub($paid, $refunded, 2);

        return bccomp($remaining, '0.00', 2) < 0 ? '0.00' : $remaining;
    }
}
