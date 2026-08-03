<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Merchandising category for filtering the product catalog.
 *
 * Supports optional parent/child hierarchy (max depth enforced in CategoryService).
 *
 * @package App\Models
 */
class Category extends Model
{
    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'icon',
        'image',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Category>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Category>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Category>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Category>
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }
}
