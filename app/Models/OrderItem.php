<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Order line item with immutable purchase price/qty snapshot.
 *
 * Fulfillment is per-item (`fulfillment_status`); ownership is via product.vendor_id.
 * Sensitive fields (order_id, product_id, price, quantity) are not fillable from
 * vendor fulfillment requests — set only at checkout / trusted server code.
 *
 * @package App\Models
 */
class OrderItem extends Model
{
    public const FULFILLMENT_STATUSES = [
        'pending',
        'confirmed',
        'processing',
        'shipped',
        'delivered',
        'cancelled',
    ];

    /**
     * Only fulfillment_status is mass-assignable for updates.
     * Checkout assigns order_id/product_id/quantity/price explicitly.
     *
     * @var list<string>
     */
    protected $fillable = [
        'fulfillment_status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function fulfillmentHistories(): HasMany
    {
        return $this->hasMany(FulfillmentStatusHistory::class);
    }

    /**
     * Create a line item at checkout with explicit column assignment
     * (avoids mass-assigning ownership/financial fields from requests).
     */
    public static function recordPurchase(int $orderId, int $productId, int $quantity, float|string $unitPrice): self
    {
        $item = new self;
        $item->order_id = $orderId;
        $item->product_id = $productId;
        $item->quantity = $quantity;
        $item->price = $unitPrice;
        $item->fulfillment_status = 'pending';
        $item->save();

        return $item;
    }

    /**
     * Line total using immutable historical unit price.
     */
    public function lineTotal(): string
    {
        return bcmul((string) $this->price, (string) $this->quantity, 2);
    }

    /**
     * Whether the given status string is a known fulfillment status.
     */
    public static function isValidFulfillmentStatus(string $status): bool
    {
        return in_array($status, self::FULFILLMENT_STATUSES, true);
    }
}
