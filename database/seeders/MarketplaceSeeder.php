<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\Review;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo catalog for SANA Market: categories, vendors, products, reviews, Q&A, coupons.
 *
 * Destructive: clears existing reviews/questions/products/vendors/categories/coupons
 * before insert so `db:seed` stays idempotent for local demos. Do not run against
 * production data without a backup.
 *
 * @package Database\Seeders
 */
class MarketplaceSeeder extends Seeder
{
    /**
     * Wipe demo marketplace rows and re-seed a curated East-Africa style catalog.
     */
    public function run(): void
    {
        // Order matters: child rows before parents when FKs cascade is not enough for truncate-style clears.
        Review::query()->delete();
        ProductQuestion::query()->delete();
        Product::query()->delete();
        Vendor::query()->delete();
        Category::query()->delete();
        Coupon::query()->delete();

        $categories = collect([
            ['name' => 'Electronics', 'slug' => 'electronics', 'icon' => 'devices', 'image' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=900&q=80', 'description' => 'Phones, laptops, audio & gadgets', 'sort_order' => 1],
            ['name' => 'Fashion', 'slug' => 'fashion', 'icon' => 'apparel', 'image' => 'https://images.unsplash.com/photo-1445205170230-053b83016050?auto=format&fit=crop&w=900&q=80', 'description' => 'Apparel, sneakers & accessories', 'sort_order' => 2],
            ['name' => 'Home & Living', 'slug' => 'home', 'icon' => 'home', 'image' => 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?auto=format&fit=crop&w=900&q=80', 'description' => 'Furniture and everyday essentials', 'sort_order' => 3],
            ['name' => 'Beauty', 'slug' => 'beauty', 'icon' => 'spa', 'image' => 'https://images.unsplash.com/photo-1596462502278-27bfdc403348?auto=format&fit=crop&w=900&q=80', 'description' => 'Skincare, fragrance & wellness', 'sort_order' => 4],
            ['name' => 'Sports', 'slug' => 'sports', 'icon' => 'sports', 'image' => 'https://images.unsplash.com/photo-1517836357463-d25dfeac3438?auto=format&fit=crop&w=900&q=80', 'description' => 'Fitness gear and outdoor kit', 'sort_order' => 5],
        ])->map(fn ($c) => Category::create($c))->keyBy('slug');

        $vendors = collect([
            ['store_name' => 'Tech Haven', 'email' => 'techhaven@sana.com', 'description' => 'Authorized electronics & gadgets', 'location' => 'Dar es Salaam', 'is_verified' => true, 'rating_avg' => 4.8, 'logo' => 'https://images.unsplash.com/photo-1560472354-b33ff0c44a43?auto=format&fit=crop&w=200&q=80'],
            ['store_name' => 'Fashion Plus', 'email' => 'fashionplus@sana.com', 'description' => 'Global streetwear & premium fashion', 'location' => 'Arusha', 'is_verified' => true, 'rating_avg' => 4.6, 'logo' => 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=200&q=80'],
            ['store_name' => 'Home Essentials', 'email' => 'homeessentials@sana.com', 'description' => 'Modern furniture for every room', 'location' => 'Mwanza', 'is_verified' => true, 'rating_avg' => 4.5, 'logo' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=200&q=80'],
            ['store_name' => 'Beauty & Wellness', 'email' => 'beauty@sana.com', 'description' => 'Clean beauty and self-care', 'location' => 'Dodoma', 'is_verified' => false, 'rating_avg' => 4.4, 'logo' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=200&q=80'],
            ['store_name' => 'Apple Authorized', 'email' => 'apple@sana.com', 'description' => 'Genuine Apple products & accessories', 'location' => 'Dar es Salaam', 'is_verified' => true, 'rating_avg' => 4.9, 'logo' => 'https://images.unsplash.com/photo-1611186871348-b1ce696e52c9?auto=format&fit=crop&w=200&q=80'],
        ])->map(fn ($v) => Vendor::create($v))->keyBy('store_name');

        // Link demo seller account to Tech Haven (1:1 ownership). user_id is not fillable.
        $seller = User::where('email', 'seller@example.com')->first();
        if ($seller) {
            $techHaven = $vendors['Tech Haven'];
            $techHaven->user_id = $seller->id;
            $techHaven->save();
        }

        // Second linked vendor account for local multi-vendor demos / IDOR checks.
        $fashionSeller = User::updateOrCreate(
            ['email' => 'fashion@example.com'],
            [
                'name' => 'Fashion Seller',
                'password' => 'password',
                'phone' => '+255700000004',
            ]
        );
        $fashionSeller->forceFill(['role' => 'vendor', 'is_active' => true])->save();
        $fashionPlus = $vendors['Fashion Plus'];
        $fashionPlus->user_id = $fashionSeller->id;
        $fashionPlus->save();

        $products = [
            [
                'vendor' => 'Tech Haven', 'category' => 'electronics', 'name' => 'Samsung Galaxy S25 Ultra', 'brand' => 'Samsung',
                'price' => 3200000, 'compare_at_price' => 3600000, 'stock' => 24, 'sold_count' => 186,
                'is_featured' => true, 'is_flash_sale' => true, 'is_new' => true,
                'image' => 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?auto=format&fit=crop&w=1000&q=80',
                'gallery' => [
                    'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?auto=format&fit=crop&w=1000&q=80',
                    'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=1000&q=80',
                    'https://images.unsplash.com/photo-1592899677977-9c10ca588bbd?auto=format&fit=crop&w=1000&q=80',
                ],
                'specs' => ['Display' => '6.9" Dynamic AMOLED', 'Chip' => 'Snapdragon 8 Elite', 'RAM' => '12GB', 'Storage' => '256GB', 'Camera' => '200MP'],
                'variants' => ['colors' => ['Titanium Black', 'Silver', 'Blue'], 'storage' => ['256GB', '512GB']],
                'description' => 'Flagship Galaxy S25 Ultra with pro-grade camera, S Pen, and all-day battery for creators and power users.',
            ],
            [
                'vendor' => 'Apple Authorized', 'category' => 'electronics', 'name' => 'iPhone 16 Pro Max', 'brand' => 'Apple',
                'price' => 3800000, 'compare_at_price' => 4100000, 'stock' => 18, 'sold_count' => 240,
                'is_featured' => true, 'is_flash_sale' => true, 'is_new' => true,
                'image' => 'https://images.unsplash.com/photo-1695048133142-1a20484d2569?auto=format&fit=crop&w=1000&q=80',
                'gallery' => [
                    'https://images.unsplash.com/photo-1695048133142-1a20484d2569?auto=format&fit=crop&w=1000&q=80',
                    'https://images.unsplash.com/photo-1510557880182-3d4d3cba35a5?auto=format&fit=crop&w=1000&q=80',
                ],
                'specs' => ['Display' => '6.9" Super Retina XDR', 'Chip' => 'A18 Pro', 'Camera' => '48MP Fusion', 'Battery' => 'All-day'],
                'variants' => ['colors' => ['Natural Titanium', 'Black Titanium'], 'storage' => ['256GB', '512GB', '1TB']],
                'description' => 'The ultimate iPhone with titanium design, cinematic camera control, and blazing A18 Pro performance.',
            ],
            [
                'vendor' => 'Apple Authorized', 'category' => 'electronics', 'name' => 'MacBook Pro 14" M4', 'brand' => 'Apple',
                'price' => 5200000, 'compare_at_price' => 5600000, 'stock' => 12, 'sold_count' => 95,
                'is_featured' => true, 'is_flash_sale' => false, 'is_new' => true,
                'image' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=1000&q=80',
                'gallery' => [
                    'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=1000&q=80',
                    'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=1000&q=80',
                ],
                'specs' => ['Chip' => 'Apple M4', 'RAM' => '16GB', 'Storage' => '512GB SSD', 'Display' => 'Liquid Retina XDR'],
                'variants' => ['colors' => ['Space Black', 'Silver']],
                'description' => 'Pro laptop for video, code, and design — silent, powerful, and built for all-day creative work.',
            ],
            [
                'vendor' => 'Tech Haven', 'category' => 'electronics', 'name' => 'Sony WH-1000XM5 Headphones', 'brand' => 'Sony',
                'price' => 780000, 'compare_at_price' => 920000, 'stock' => 40, 'sold_count' => 310,
                'is_featured' => true, 'is_flash_sale' => true, 'is_new' => false,
                'image' => 'https://images.unsplash.com/photo-1618366712010-f4ae9c647dcb?auto=format&fit=crop&w=1000&q=80',
                'gallery' => [
                    'https://images.unsplash.com/photo-1618366712010-f4ae9c647dcb?auto=format&fit=crop&w=1000&q=80',
                    'https://images.unsplash.com/photo-1546435770-a3e426bf472b?auto=format&fit=crop&w=1000&q=80',
                ],
                'specs' => ['ANC' => 'Industry-leading', 'Battery' => '30 hours', 'Connectivity' => 'Bluetooth 5.3'],
                'variants' => ['colors' => ['Black', 'Silver']],
                'description' => 'Premium noise-cancelling headphones with crystal clarity for flights, offices, and deep focus.',
            ],
            [
                'vendor' => 'Tech Haven', 'category' => 'electronics', 'name' => 'iPad Air 11" M2', 'brand' => 'Apple',
                'price' => 1800000, 'compare_at_price' => 1990000, 'stock' => 30, 'sold_count' => 140,
                'is_featured' => false, 'is_flash_sale' => false, 'is_new' => true,
                'image' => 'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?auto=format&fit=crop&w=1000&q=80',
                'gallery' => ['https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?auto=format&fit=crop&w=1000&q=80'],
                'specs' => ['Chip' => 'M2', 'Display' => '11" Liquid Retina', 'Pencil' => 'Apple Pencil Pro'],
                'variants' => ['colors' => ['Blue', 'Purple', 'Starlight']],
                'description' => 'Thin, powerful tablet for notes, streaming, and creative apps — with Apple Pencil support.',
            ],
            [
                'vendor' => 'Fashion Plus', 'category' => 'fashion', 'name' => 'Nike Air Max 270', 'brand' => 'Nike',
                'price' => 285000, 'compare_at_price' => 340000, 'stock' => 55, 'sold_count' => 420,
                'is_featured' => true, 'is_flash_sale' => true, 'is_new' => false,
                'image' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=1000&q=80',
                'gallery' => [
                    'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=1000&q=80',
                    'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?auto=format&fit=crop&w=1000&q=80',
                ],
                'specs' => ['Type' => 'Lifestyle sneaker', 'Upper' => 'Mesh & synthetic', 'Sole' => 'Max Air unit'],
                'variants' => ['sizes' => ['40', '41', '42', '43', '44'], 'colors' => ['Red/White', 'Black']],
                'description' => 'Iconic Air Max comfort with bold street style — everyday sneakers that turn heads.',
            ],
            [
                'vendor' => 'Fashion Plus', 'category' => 'fashion', 'name' => 'Adidas Ultraboost Light', 'brand' => 'Adidas',
                'price' => 320000, 'compare_at_price' => 380000, 'stock' => 48, 'sold_count' => 275,
                'is_featured' => false, 'is_flash_sale' => true, 'is_new' => false,
                'image' => 'https://images.unsplash.com/photo-1608231387042-66d1773070a5?auto=format&fit=crop&w=1000&q=80',
                'gallery' => ['https://images.unsplash.com/photo-1608231387042-66d1773070a5?auto=format&fit=crop&w=1000&q=80'],
                'specs' => ['Use' => 'Running / lifestyle', 'Cushion' => 'Boost Light', 'Fit' => 'Primeknit'],
                'variants' => ['sizes' => ['40', '41', '42', '43', '44', '45']],
                'description' => 'Energy-returning Ultraboost Light for long runs and all-day city miles.',
            ],
            [
                'vendor' => 'Fashion Plus', 'category' => 'fashion', 'name' => 'Levi\'s 501 Original Jeans', 'brand' => 'Levi\'s',
                'price' => 145000, 'compare_at_price' => 175000, 'stock' => 80, 'sold_count' => 190,
                'is_featured' => false, 'is_flash_sale' => false, 'is_new' => false,
                'image' => 'https://images.unsplash.com/photo-1542272604-787c3835535d?auto=format&fit=crop&w=1000&q=80',
                'gallery' => ['https://images.unsplash.com/photo-1542272604-787c3835535d?auto=format&fit=crop&w=1000&q=80'],
                'specs' => ['Fit' => 'Straight', 'Material' => '100% cotton denim', 'Rise' => 'Mid'],
                'variants' => ['sizes' => ['28', '30', '32', '34', '36'], 'colors' => ['Indigo', 'Black']],
                'description' => 'The original straight-leg jean — durable denim with timeless Americana style.',
            ],
            [
                'vendor' => 'Fashion Plus', 'category' => 'fashion', 'name' => 'Leather Bomber Jacket', 'brand' => 'SANA Atelier',
                'price' => 420000, 'compare_at_price' => 510000, 'stock' => 22, 'sold_count' => 68,
                'is_featured' => true, 'is_flash_sale' => false, 'is_new' => true,
                'image' => 'https://images.unsplash.com/photo-1551028719-00167b16eac5?auto=format&fit=crop&w=1000&q=80',
                'gallery' => ['https://images.unsplash.com/photo-1551028719-00167b16eac5?auto=format&fit=crop&w=1000&q=80'],
                'specs' => ['Material' => 'Genuine leather', 'Lining' => 'Quilted', 'Fit' => 'Regular'],
                'variants' => ['sizes' => ['S', 'M', 'L', 'XL'], 'colors' => ['Brown', 'Black']],
                'description' => 'Premium leather bomber with quilted lining — sharp enough for nights out, tough enough for daily wear.',
            ],
            [
                'vendor' => 'Home Essentials', 'category' => 'home', 'name' => 'Scandinavian Oak Dining Set', 'brand' => 'NordHome',
                'price' => 980000, 'compare_at_price' => 1150000, 'stock' => 8, 'sold_count' => 34,
                'is_featured' => true, 'is_flash_sale' => false, 'is_new' => false,
                'image' => 'https://images.unsplash.com/photo-1617806118233-18e1de247200?auto=format&fit=crop&w=1000&q=80',
                'gallery' => ['https://images.unsplash.com/photo-1617806118233-18e1de247200?auto=format&fit=crop&w=1000&q=80'],
                'specs' => ['Seats' => '6', 'Material' => 'Solid oak', 'Finish' => 'Natural matte'],
                'variants' => ['colors' => ['Natural Oak', 'Walnut']],
                'description' => 'Solid oak dining table with six chairs — warm Scandinavian lines for family meals and hosting.',
            ],
            [
                'vendor' => 'Home Essentials', 'category' => 'home', 'name' => 'Ergonomic Mesh Office Chair', 'brand' => 'WorkWell',
                'price' => 365000, 'compare_at_price' => 420000, 'stock' => 35, 'sold_count' => 155,
                'is_featured' => false, 'is_flash_sale' => true, 'is_new' => false,
                'image' => 'https://images.unsplash.com/photo-1580480055273-228ff5388ef8?auto=format&fit=crop&w=1000&q=80',
                'gallery' => ['https://images.unsplash.com/photo-1580480055273-228ff5388ef8?auto=format&fit=crop&w=1000&q=80'],
                'specs' => ['Support' => 'Lumbar adjustable', 'Arms' => '3D adjustable', 'Wheels' => 'Silent caster'],
                'variants' => ['colors' => ['Black', 'Grey']],
                'description' => 'All-day ergonomic chair with breathable mesh and adjustable lumbar support for hybrid work.',
            ],
            [
                'vendor' => 'Home Essentials', 'category' => 'home', 'name' => 'Minimalist Ceramic Table Lamp', 'brand' => 'Lumen',
                'price' => 89000, 'compare_at_price' => 110000, 'stock' => 60, 'sold_count' => 210,
                'is_featured' => false, 'is_flash_sale' => false, 'is_new' => true,
                'image' => 'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?auto=format&fit=crop&w=1000&q=80',
                'gallery' => ['https://images.unsplash.com/photo-1507473885765-e6ed057f782c?auto=format&fit=crop&w=1000&q=80'],
                'specs' => ['Bulb' => 'E27 LED included', 'Height' => '45cm', 'Material' => 'Ceramic + linen'],
                'variants' => ['colors' => ['Ivory', 'Sage', 'Charcoal']],
                'description' => 'Soft ambient lamp with ceramic base and linen shade — perfect bedside or desk glow.',
            ],
            [
                'vendor' => 'Beauty & Wellness', 'category' => 'beauty', 'name' => 'Vitamin C Brightening Set', 'brand' => 'GlowLab',
                'price' => 98000, 'compare_at_price' => 125000, 'stock' => 90, 'sold_count' => 330,
                'is_featured' => true, 'is_flash_sale' => true, 'is_new' => false,
                'image' => 'https://images.unsplash.com/photo-1556228578-0d85b1a4d571?auto=format&fit=crop&w=1000&q=80',
                'gallery' => ['https://images.unsplash.com/photo-1556228578-0d85b1a4d571?auto=format&fit=crop&w=1000&q=80'],
                'specs' => ['Includes' => 'Cleanser, serum, moisturizer', 'Skin' => 'All types', 'Concern' => 'Dullness'],
                'variants' => [],
                'description' => 'Complete brightening routine with stable vitamin C for clearer, more radiant skin.',
            ],
            [
                'vendor' => 'Beauty & Wellness', 'category' => 'beauty', 'name' => 'Luxury Eau de Parfum 100ml', 'brand' => 'Noir Atelier',
                'price' => 210000, 'compare_at_price' => 260000, 'stock' => 40, 'sold_count' => 120,
                'is_featured' => false, 'is_flash_sale' => false, 'is_new' => true,
                'image' => 'https://images.unsplash.com/photo-1541643600914-78b084683601?auto=format&fit=crop&w=1000&q=80',
                'gallery' => ['https://images.unsplash.com/photo-1541643600914-78b084683601?auto=format&fit=crop&w=1000&q=80'],
                'specs' => ['Notes' => 'Bergamot, cedar, amber', 'Concentration' => 'EDP', 'Size' => '100ml'],
                'variants' => [],
                'description' => 'Long-lasting unisex fragrance with citrus opening and warm woody dry-down.',
            ],
            [
                'vendor' => 'Beauty & Wellness', 'category' => 'sports', 'name' => 'Premium Yoga Mat Pro', 'brand' => 'FlexForm',
                'price' => 125000, 'compare_at_price' => 155000, 'stock' => 70, 'sold_count' => 260,
                'is_featured' => false, 'is_flash_sale' => true, 'is_new' => false,
                'image' => 'https://images.unsplash.com/photo-1601925260368-ae2f83cf8b7f?auto=format&fit=crop&w=1000&q=80',
                'gallery' => ['https://images.unsplash.com/photo-1601925260368-ae2f83cf8b7f?auto=format&fit=crop&w=1000&q=80'],
                'specs' => ['Thickness' => '6mm', 'Material' => 'Eco TPE', 'Grip' => 'Non-slip'],
                'variants' => ['colors' => ['Ocean', 'Slate', 'Coral']],
                'description' => 'Extra-grip yoga mat with dense cushioning for studio classes and home practice.',
            ],
            [
                'vendor' => 'Tech Haven', 'category' => 'electronics', 'name' => 'Samsung 55" QLED 4K TV', 'brand' => 'Samsung',
                'price' => 2100000, 'compare_at_price' => 2450000, 'stock' => 14, 'sold_count' => 72,
                'is_featured' => true, 'is_flash_sale' => false, 'is_new' => false,
                'image' => 'https://images.unsplash.com/photo-1593359677879-a4bb0b16b25d?auto=format&fit=crop&w=1000&q=80',
                'gallery' => ['https://images.unsplash.com/photo-1593359677879-a4bb0b16b25d?auto=format&fit=crop&w=1000&q=80'],
                'specs' => ['Resolution' => '4K QLED', 'HDR' => 'HDR10+', 'Smart' => 'Tizen OS'],
                'variants' => [],
                'description' => 'Immersive QLED picture with Quantum HDR and smart streaming apps built in.',
            ],
        ];

        $flashEnd = now()->addHours(18);

        foreach ($products as $data) {
            $product = new Product([
                'category_id' => $categories[$data['category']]->id,
                'name' => $data['name'],
                'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
                'brand' => $data['brand'],
                'price' => $data['price'],
                'compare_at_price' => $data['compare_at_price'],
                'stock' => $data['stock'],
                'is_featured' => $data['is_featured'],
                'is_flash_sale' => $data['is_flash_sale'],
                'flash_ends_at' => $data['is_flash_sale'] ? $flashEnd : null,
                'is_new' => $data['is_new'],
                'description' => $data['description'],
                'image' => $data['image'],
                'gallery' => $data['gallery'],
                'specs' => $data['specs'],
                'variants' => $data['variants'],
                'sku' => 'SKU-'.strtoupper(Str::random(8)),
            ]);
            $product->vendor_id = $vendors[$data['vendor']]->id;
            $product->save();

            // Aggregates are guarded from mass assignment; set explicitly for demo data.
            $product->forceFill([
                'sold_count' => $data['sold_count'],
                'rating_avg' => round(mt_rand(40, 50) / 10, 1),
                'rating_count' => mt_rand(12, 240),
            ])->save();

            Review::create([
                'product_id' => $product->id,
                'author_name' => 'Amina K.',
                'rating' => 5,
                'title' => 'Excellent quality',
                'body' => 'Arrived quickly and exactly as described. Would buy again from this seller.',
            ]);

            Review::create([
                'product_id' => $product->id,
                'author_name' => 'James M.',
                'rating' => 4,
                'title' => 'Great value',
                'body' => 'Solid product for the price. Packaging was secure and support responded fast.',
            ]);

            ProductQuestion::create([
                'product_id' => $product->id,
                'author_name' => 'Buyer',
                'question' => 'Does this include warranty and local delivery to Dar es Salaam?',
                'answer' => 'Yes — 12-month seller warranty and delivery within Dar in 1–3 business days.',
            ]);
        }

        Coupon::create([
            'code' => 'SANA10',
            'type' => 'percent',
            'value' => 10,
            'min_order' => 50000,
            'expires_at' => now()->addMonths(3),
            'is_active' => true,
        ]);

        Coupon::create([
            'code' => 'FLASH50K',
            'type' => 'fixed',
            'value' => 50000,
            'min_order' => 300000,
            'expires_at' => now()->addMonth(),
            'is_active' => true,
        ]);
    }
}
