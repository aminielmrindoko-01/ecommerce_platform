<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Merchandising category for filtering the product catalog.
 *
 * `sort_order` controls nav/home display order; `slug` is used in query filters.
 *
 * @package App\Models
 */
class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'icon',
        'image',
        'description',
        'sort_order',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
