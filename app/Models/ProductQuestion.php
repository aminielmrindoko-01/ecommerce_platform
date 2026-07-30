<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Buyer Q&A on a product. `answer` remains null until a seller/admin replies.
 *
 * @package App\Models
 */
class ProductQuestion extends Model
{
    protected $fillable = [
        'product_id',
        'user_id',
        'author_name',
        'question',
        'answer',
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
