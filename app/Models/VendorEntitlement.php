<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-order-item vendor entitlement with frozen commission snapshot.
 */
class VendorEntitlement extends Model
{
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'commission_rate' => 'decimal:4',
            'commission_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'refunded_gross' => 'decimal:2',
            'refunded_commission' => 'decimal:2',
            'refunded_net' => 'decimal:2',
            'calculation_snapshot' => 'array',
            'available_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    public function remainingNet(): string
    {
        $net = bcadd((string) ($this->getAttributes()['net_amount'] ?? '0'), '0', 2);
        $refunded = bcadd((string) ($this->getAttributes()['refunded_net'] ?? '0'), '0', 2);
        $remaining = bcsub($net, $refunded, 2);

        return bccomp($remaining, '0.00', 2) < 0 ? '0.00' : $remaining;
    }
}
