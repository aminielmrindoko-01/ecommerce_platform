<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Promotional coupon applied to session cart / checkout.
 *
 * type `percent` interprets `value` as a percentage; `fixed` as TZS amount
 * capped at the current subtotal. Validity requires active + not expired + min_order.
 *
 * @package App\Models
 */
class Coupon extends Model
{
    protected $fillable = [
        'code',
        'type',
        'value',
        'min_order',
        'expires_at',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
            'value' => 'decimal:2',
            'min_order' => 'decimal:2',
        ];
    }

    /**
     * Whether this coupon may be applied to the given cart subtotal (TZS).
     */
    public function isValid(float $subtotal): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return $subtotal >= (float) $this->min_order;
    }

    /**
     * Discount amount in TZS for a valid coupon; 0 when invalid.
     */
    public function discountFor(float $subtotal): float
    {
        if (! $this->isValid($subtotal)) {
            return 0;
        }

        if ($this->type === 'fixed') {
            return min((float) $this->value, $subtotal);
        }

        return round($subtotal * ((float) $this->value / 100), 2);
    }
}
