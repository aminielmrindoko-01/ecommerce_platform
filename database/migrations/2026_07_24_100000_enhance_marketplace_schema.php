<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('vendor_id')->constrained()->nullOnDelete();
            $table->string('slug')->nullable()->unique()->after('name');
            $table->string('brand')->nullable()->after('slug');
            $table->decimal('compare_at_price', 12, 2)->nullable()->after('price');
            $table->decimal('rating_avg', 3, 2)->default(0)->after('stock');
            $table->unsignedInteger('rating_count')->default(0)->after('rating_avg');
            $table->boolean('is_featured')->default(false)->after('rating_count');
            $table->boolean('is_flash_sale')->default(false)->after('is_featured');
            $table->timestamp('flash_ends_at')->nullable()->after('is_flash_sale');
            $table->boolean('is_new')->default(false)->after('flash_ends_at');
            $table->json('gallery')->nullable()->after('image');
            $table->json('specs')->nullable()->after('gallery');
            $table->json('variants')->nullable()->after('specs');
            $table->string('sku')->nullable()->after('variants');
            $table->unsignedInteger('sold_count')->default(0)->after('sku');
            $table->index(['category_id', 'brand']);
            $table->index(['is_featured', 'is_flash_sale', 'is_new']);
            $table->index('price');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->string('logo')->nullable()->after('email');
            $table->string('location')->nullable()->after('logo');
            $table->decimal('rating_avg', 3, 2)->default(4.5)->after('is_verified');
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name')->nullable();
            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('body');
            $table->timestamps();
            $table->index(['product_id', 'rating']);
        });

        Schema::create('product_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name')->nullable();
            $table->text('question');
            $table->text('answer')->nullable();
            $table->timestamps();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type')->default('percent'); // percent|fixed
            $table->decimal('value', 10, 2);
            $table->decimal('min_order', 12, 2)->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label')->default('Home');
            $table->string('full_name');
            $table->string('phone');
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city');
            $table->string('region')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->default('Tanzania');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('status');
            $table->string('shipping_method')->nullable()->after('payment_method');
            $table->decimal('shipping_cost', 12, 2)->default(0)->after('shipping_method');
            $table->decimal('tax_amount', 12, 2)->default(0)->after('shipping_cost');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('tax_amount');
            $table->string('coupon_code')->nullable()->after('discount_amount');
            $table->json('shipping_address')->nullable()->after('coupon_code');
            $table->string('order_number')->nullable()->unique()->after('id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('avatar')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'avatar']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'payment_method', 'shipping_method', 'shipping_cost', 'tax_amount',
                'discount_amount', 'coupon_code', 'shipping_address', 'order_number',
            ]);
        });

        Schema::dropIfExists('addresses');
        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('product_questions');
        Schema::dropIfExists('reviews');

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['logo', 'location', 'rating_avg']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn([
                'slug', 'brand', 'compare_at_price', 'rating_avg', 'rating_count',
                'is_featured', 'is_flash_sale', 'flash_ends_at', 'is_new',
                'gallery', 'specs', 'variants', 'sku', 'sold_count',
            ]);
        });

        Schema::dropIfExists('categories');
    }
};
