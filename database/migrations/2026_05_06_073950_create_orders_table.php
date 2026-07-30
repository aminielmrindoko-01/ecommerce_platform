<?php

/**
 * Order headers: buyer FK cascades so user deletion removes their orders.
 * Status enum models fulfillment lifecycle used by admin + revenue KPIs.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create orders table.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('total_price', 10, 2);
            $table->enum('status', ['pending', 'paid', 'shipped', 'completed'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Drop orders.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
