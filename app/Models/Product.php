<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'vendor_id',
        'name',
        'price',
        'stock',
        'description'
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}