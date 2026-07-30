<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Buyer order header: totals, status lifecycle, payment/shipping snapshot.
 *
 * `shipping_address` is JSON so historical addresses survive address-book edits.
 * Status enum values: pending, paid, shipped, completed.
 *
 * @package App\Models
 */
class Order extends Model
{
    protected $fillable = [
        'order_number',
        'user_id',
        'total_price',
        'status',
        'payment_method',
        'shipping_method',
        'shipping_cost',
        'tax_amount',
        'discount_amount',
        'coupon_code',
        'shipping_address',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_price' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'shipping_address' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Alias for Blade/templates that expect `$order->total`.
     *
     * @return mixed
     */
    public function getTotalAttribute()
    {
        return $this->total_price;
    }
}
