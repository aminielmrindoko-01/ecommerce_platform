<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vendors = Vendor::all();

        // Tech Haven Products
        Product::create([
            'vendor_id' => $vendors->firstWhere('store_name', 'Tech Haven')->id,
            'name' => 'Wireless Headphones Pro',
            'description' => 'High-quality wireless headphones with noise cancellation. 30-hour battery life, premium sound quality, and ergonomic design for all-day comfort.',
            'price' => 245000,
            'stock' => 45,
            'image' => 'headphone.jpg',
        ]);

        Product::create([
            'vendor_id' => $vendors->firstWhere('store_name', 'Tech Haven')->id,
            'name' => 'Smartphone X12',
            'description' => 'Latest smartphone with 5G connectivity, 120Hz AMOLED display, advanced camera system, and long-lasting battery.',
            'price' => 850000,
            'stock' => 28,
            'image' => 'phone1.jpg',
        ]);

        Product::create([
            'vendor_id' => $vendors->firstWhere('store_name', 'Tech Haven')->id,
            'name' => 'Tablet Pro 11"',
            'description' => 'Powerful tablet with M2 chip, 11-inch display, pencil support, and perfect for creative professionals.',
            'price' => 620000,
            'stock' => 35,
            'image' => 'phone1.jpg',
        ]);

        Product::create([
            'vendor_id' => $vendors->firstWhere('store_name', 'Tech Haven')->id,
            'name' => 'Laptop Ultra 15',
            'description' => 'Ultra-thin laptop with Intel i7, 16GB RAM, 512GB SSD, perfect for work and entertainment.',
            'price' => 1200000,
            'stock' => 18,
            'image' => 'headphone.jpg',
        ]);

        // Fashion Plus Products
        Product::create([
            'vendor_id' => $vendors->firstWhere('store_name', 'Fashion Plus')->id,
            'name' => 'Designer T-Shirt',
            'description' => 'Premium cotton t-shirt with modern design. Comfortable fit, high-quality fabric, available in multiple colors.',
            'price' => 45000,
            'stock' => 120,
            'image' => 'phone1.jpg',
        ]);

        Product::create([
            'vendor_id' => $vendors->firstWhere('store_name', 'Fashion Plus')->id,
            'name' => 'Casual Jeans',
            'description' => 'Classic blue jeans with perfect fit. Durable denim, stylish design, comfortable for everyday wear.',
            'price' => 85000,
            'stock' => 95,
            'image' => 'phone1.jpg',
        ]);

        Product::create([
            'vendor_id' => $vendors->firstWhere('store_name', 'Fashion Plus')->id,
            'name' => 'Leather Jacket',
            'description' => 'Genuine leather jacket with modern cut. Premium quality, weather-resistant, perfect for any season.',
            'price' => 380000,
            'stock' => 22,
            'image' => 'headphone.jpg',
        ]);

        Product::create([
            'vendor_id' => $vendors->firstWhere('store_name', 'Fashion Plus')->id,
            'name' => 'Sports Shoes',
            'description' => 'Comfortable running shoes with advanced cushioning. Breathable material, lightweight design, great support.',
            'price' => 120000,
            'stock' => 60,
            'image' => 'phone1.jpg',
        ]);

        // Home Essentials Products
        Product::create([
            'vendor_id' => $vendors->firstWhere('store_name', 'Home Essentials')->id,
            'name' => 'Coffee Table Modern',
            'description' => 'Sleek modern coffee table with tempered glass top. Wooden frame, contemporary design, perfect centerpiece.',
            'price' => 280000,
            'stock' => 15,
            'image' => 'headphone.jpg',
        ]);

        Product::create([
            'vendor_id' => $vendors->firstWhere('store_name', 'Home Essentials')->id,
            'name' => 'Office Chair Ergonomic',
            'description' => 'Premium ergonomic office chair with lumbar support. Adjustable height, swivel base, perfect for long hours.',
            'price' => 320000,
            'stock' => 32,
            'image' => 'phone1.jpg',
        ]);

        Product::create([
            'vendor_id' => $vendors->firstWhere('store_name', 'Home Essentials')->id,
            'name' => 'Bookshelf 5-Tier',
            'description' => 'Spacious 5-tier bookshelf with sturdy construction. Perfect for storage and display, modern design.',
            'price' => 195000,
            'stock' => 28,
            'image' => 'phone1.jpg',
        ]);

        Product::create([
            'vendor_id' => $vendors->firstWhere('store_name', 'Home Essentials')->id,
            'name' => 'Dining Set (6-Seat)',
            'description' => 'Elegant dining table with 6 chairs. Solid wood construction, classic design, perfect for families.',
            'price' => 850000,
            'stock' => 10,
            'image' => 'headphone.jpg',
        ]);

        // Beauty & Wellness Products
        Product::create([
            'vendor_id' => $vendors->firstWhere('store_name', 'Beauty & Wellness')->id,
            'name' => 'Facial Skincare Set',
            'description' => 'Complete skincare routine with 4 premium products. Cleaner, toner, serum, and moisturizer for all skin types.',
            'price' => 95000,
            'stock' => 80,
            'image' => 'phone1.jpg',
        ]);

        Product::create([
            'vendor_id' => $vendors->firstWhere('store_name', 'Beauty & Wellness')->id,
            'name' => 'Premium Shampoo & Conditioner',
            'description' => 'Luxurious hair care duo with natural ingredients. Strengthens, nourishes, adds shine to all hair types.',
            'price' => 68000,
            'stock' => 110,
            'image' => 'headphone.jpg',
        ]);

        Product::create([
            'vendor_id' => $vendors->firstWhere('store_name', 'Beauty & Wellness')->id,
            'name' => 'Yoga Mat Premium',
            'description' => 'Professional yoga mat with non-slip surface. Extra thick padding, eco-friendly material, perfect for all exercises.',
            'price' => 145000,
            'stock' => 50,
            'image' => 'phone1.jpg',
        ]);

        Product::create([
            'vendor_id' => $vendors->firstWhere('store_name', 'Beauty & Wellness')->id,
            'name' => 'Vitamin Supplements',
            'description' => 'Complete daily vitamin supplement pack. Essential vitamins and minerals for better health and immunity.',
            'price' => 52000,
            'stock' => 200,
            'image' => 'headphone.jpg',
        ]);
    }
}
