<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Customer return (RMA) for one vendor's line items on an order.
 */
class ReturnRequest extends Model
{
    public const STATUSES = [
        'requested',
        'approved',
        'rejected',
        'received',
        'refunded',
        'cancelled',
    ];

    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'restocked' => 'boolean',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'received_at' => 'datetime',
            'refunded_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class);
    }

    public function paymentRefund(): BelongsTo
    {
        return $this->belongsTo(PaymentRefund::class);
    }

    public function settlementHold(): BelongsTo
    {
        return $this->belongsTo(SettlementHold::class);
    }

    public function refundAmount(): string
    {
        $sum = '0.00';
        foreach ($this->items as $item) {
            $sum = bcadd($sum, (string) ($item->getAttributes()['line_amount'] ?? '0'), 2);
        }

        return $sum;
    }

    public static function isValidStatus(string $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }
}
