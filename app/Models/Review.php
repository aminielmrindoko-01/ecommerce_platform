<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Product review with moderation workflow.
 *
 * Customer content fields must not be overwritten by moderators.
 */
class Review extends Model
{
    public const STATUS_PENDING = 'PENDING';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    public const STATUS_HIDDEN = 'HIDDEN';

    public const STATUS_FLAGGED = 'FLAGGED';

    protected $fillable = [
        'product_id',
        'user_id',
        'author_name',
        'rating',
        'title',
        'body',
        // status / moderation fields are NOT mass-assignable from requests
    ];

    protected function casts(): array
    {
        return [
            'verified_purchase' => 'boolean',
            'moderated_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function isVisiblePublicly(): bool
    {
        return in_array($this->status ?: self::STATUS_APPROVED, [self::STATUS_APPROVED], true);
    }
}
