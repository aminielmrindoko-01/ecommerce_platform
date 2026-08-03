<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only inventory movement / adjustment history.
 * Ordinary users must not update or delete rows.
 */
class InventoryMovement extends Model
{
    public $timestamps = false;

    public const TYPE_INITIAL = 'initial';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_DAMAGE = 'damage';

    public const TYPE_RETURN = 'return';

    public const TYPE_SALE = 'sale';

    public const TYPE_RESERVE = 'reserve';

    public const TYPE_RELEASE = 'release';

    protected $fillable = [
        'product_id',
        'actor_user_id',
        'type',
        'quantity_before',
        'quantity_delta',
        'quantity_after',
        'reserved_before',
        'reserved_after',
        'reason',
        'reference_type',
        'reference_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Inventory movements are immutable.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Inventory movements are immutable.');
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
