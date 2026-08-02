<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit row for a fulfillment status change.
 */
class FulfillmentStatusHistory extends Model
{
    protected $fillable = [
        'order_item_id',
        'actor_user_id',
        'from_status',
        'to_status',
        'actor_role',
        'reason',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
