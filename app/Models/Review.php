<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Product review with 1–5 rating. Guest reviews allowed (nullable user_id).
 *
 * Cascades when the product is deleted; user set null on user delete.
 *
 * @package App\Models
 */
class Review extends Model
{
    protected $fillable = [
        'product_id',
        'user_id',
        'author_name',
        'rating',
        'title',
        'body',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
