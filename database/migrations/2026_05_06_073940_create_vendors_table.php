<?php

/**
 * Seller/store registry. Verification flag is toggled from the admin console.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create vendors table.
     */
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('store_name');
            $table->string('email')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Drop vendors.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
