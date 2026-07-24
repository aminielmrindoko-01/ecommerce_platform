<?php

namespace Database\Seeders;

use App\Models\Vendor;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Legacy vendor seeder. Prefer MarketplaceSeeder which seeds vendors + richer catalog.
 *
 * @package Database\Seeders
 */
class VendorSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Create a few demo vendor stores.
     */
    public function run(): void
    {
        Vendor::create([
            'store_name' => 'Tech Haven',
            'description' => 'Premium electronics and gadgets',
            'email' => 'techhaven@sana.com',
        ]);

        Vendor::create([
            'store_name' => 'Fashion Plus',
            'description' => 'Latest trends in clothing and accessories',
            'email' => 'fashionplus@sana.com',
        ]);

        Vendor::create([
            'store_name' => 'Home Essentials',
            'description' => 'Quality furniture and home decor',
            'email' => 'homeessentials@sana.com',
        ]);

        Vendor::create([
            'store_name' => 'Beauty & Wellness',
            'description' => 'Beauty products and wellness items',
            'email' => 'beauty@sana.com',
        ]);
    }
}
