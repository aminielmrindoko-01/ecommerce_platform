<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Product;

class Vendor extends Model
{
    protected $fillable = [
        'store_name',
        'description',
        'email',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
