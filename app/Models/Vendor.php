<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Seller/store entity owned by one user and owning many products.
 *
 * Lifecycle status (pending → approved / suspended / …) is authoritative.
 * `is_verified` is kept in sync for legacy UI badges.
 *
 * @package App\Models
 */
class Vendor extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_INACTIVE = 'inactive';

    /**
     * Mass-assignable store profile fields.
     * Ownership and trust fields are set only by trusted server/admin logic.
     *
     * @var list<string>
     */
    protected $fillable = [
        'store_name',
        'description',
        'email',
        'logo',
        'location',
        // Not fillable: user_id, is_verified, status, rating_avg, reviewed_*
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'rating_avg' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isApproved(): bool
    {
        return ($this->status ?? null) === self::STATUS_APPROVED || $this->is_verified;
    }

    public function canSell(): bool
    {
        return $this->isApproved();
    }
}
