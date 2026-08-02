<?php

/**
 * Audit trail for order-item fulfillment transitions.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fulfillment_status_histories')) {
            return;
        }

        Schema::create('fulfillment_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->string('actor_role', 32)->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestamps();

            $table->index(['order_item_id', 'created_at']);
            $table->index('actor_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_status_histories');
    }
};
