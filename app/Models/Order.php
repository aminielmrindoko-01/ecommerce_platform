<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Buyer order header: totals, admin status, payment status, shipping snapshot.
 *
 * `orders.status` remains the legacy admin/order lifecycle.
 * `orders.payment_status` is the dedicated payment state machine.
 *
 * @package App\Models
 */
class Order extends Model
{
    public const PAYMENT_STATUSES = [
        'pending',
        'initiated',
        'processing',
        'paid',
        'failed',
        'cancelled',
        'expired',
        'refunded',
        'partially_refunded',
    ];

    /** Marketplace fulfillment lifecycle (payment is separate). */
    public const STATUSES = [
        'pending',
        'confirmed',
        'processing',
        'ready_for_fulfillment',
        'shipped',
        'delivered',
        'completed',
        'cancelled',
        'refunded',
        'paid', // legacy
    ];

    protected $fillable = [
        'order_number',
        'user_id',
        'total_price',
        'currency',
        'status',
        'payment_status',
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

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function latestPaymentTransaction(): HasOne
    {
        return $this->hasOne(PaymentTransaction::class)->latestOfMany();
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
