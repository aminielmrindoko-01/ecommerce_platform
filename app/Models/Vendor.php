<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Seller/store entity owned by one user and owning many products.
 *
 * Verification drives trust badges. Ownership is via user_id (1:1).
 *
 * @package App\Models
 */
class Vendor extends Model
{
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
        // Not fillable: user_id, is_verified, rating_avg
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'rating_avg' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
