<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Catalog product sold by a vendor, optionally filed under a category.
 *
 * Prices are TZS. Merchandising flags (featured / flash / new) drive homepage rails.
 * rating_avg and rating_count are denormalized from reviews for fast filtering/sort.
 *
 * @package App\Models
 */
class Product extends Model
{
    /**
     * Mass-assignable catalog fields.
     * Aggregates (rating_avg, rating_count, sold_count) are not fillable —
     * they are updated only by review/checkout server logic.
     *
     * @var list<string>
     */
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'brand',
        'price',
        'compare_at_price',
        'stock',
        'is_featured',
        'is_flash_sale',
        'flash_ends_at',
        'is_new',
        'description',
        'image',
        'gallery',
        'specs',
        'variants',
        'sku',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'rating_avg' => 'decimal:2',
            'is_featured' => 'boolean',
            'is_flash_sale' => 'boolean',
            'is_new' => 'boolean',
            'flash_ends_at' => 'datetime',
            'gallery' => 'array',
            'specs' => 'array',
            'variants' => 'array',
        ];
    }

    /**
     * Auto-generate a unique-ish slug when creating without one.
     */
    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (blank($product->slug)) {
                $product->slug = Str::slug($product->name).'-'.Str::random(5);
            }
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ProductQuestion::class);
    }

    /**
     * Resolve a displayable image URL for remote, storage, or public/images paths.
     *
     * Falls back to a stock Unsplash placeholder when no image is set.
     */
    public function getImageUrlAttribute(): string
    {
        $image = $this->image;

        if (! $image) {
            return 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=800&q=80';
        }

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        if (str_starts_with($image, 'products/')) {
            return Storage::disk('public')->url($image);
        }

        return asset('images/'.$image);
    }

    /**
     * Map gallery paths the same way as image_url; empty gallery returns [image_url].
     *
     * @return list<string>
     */
    public function getGalleryUrlsAttribute(): array
    {
        $gallery = $this->gallery ?? [];

        if (empty($gallery)) {
            return [$this->image_url];
        }

        return collect($gallery)->map(function ($image) {
            if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
                return $image;
            }

            if (str_starts_with($image, 'products/')) {
                return Storage::disk('public')->url($image);
            }

            return asset('images/'.$image);
        })->all();
    }

    /**
     * Percent off vs compare_at_price, or null when not discounted.
     */
    public function getDiscountPercentAttribute(): ?int
    {
        if (! $this->compare_at_price || $this->compare_at_price <= $this->price) {
            return null;
        }

        return (int) round((1 - ((float) $this->price / (float) $this->compare_at_price)) * 100);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Product>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Product>
     */
    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Product>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Product>
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Active flash sales: flagged and not past flash_ends_at (null end = open-ended).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Product>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Product>
     */
    public function scopeFlashSale($query)
    {
        return $query->where('is_flash_sale', true)
            ->where(function ($q) {
                $q->whereNull('flash_ends_at')->orWhere('flash_ends_at', '>', now());
            });
    }
}
