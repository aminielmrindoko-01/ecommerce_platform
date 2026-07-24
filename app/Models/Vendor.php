<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    protected $fillable = [
        'store_name',
        'description',
        'email',
        'logo',
        'location',
        'is_verified',
        'rating_avg',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'rating_avg' => 'decimal:2',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
