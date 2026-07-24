<?php

/**
 * Core products table: belongs to a vendor (cascade delete removes orphan listings).
 * Extended later by enhance_marketplace_schema for categories, ratings, etc.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the products catalog table.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            // Cascade: deleting a vendor removes their catalog rows.
            $table->foreignId('vendor_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->integer('stock');
            $table->string('image')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Drop products.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
