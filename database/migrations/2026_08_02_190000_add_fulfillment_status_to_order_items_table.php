<?php

/**
 * Vendor-specific fulfillment on line items.
 *
 * orders.status remains payment/admin lifecycle.
 * order_items.fulfillment_status tracks per-vendor item progress.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add fulfillment_status with default pending + index.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'fulfillment_status')) {
                $table->string('fulfillment_status', 32)
                    ->default('pending')
                    ->after('price');

                $table->index('fulfillment_status');
            }
        });
    }

    /**
     * Drop fulfillment_status column and index.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'fulfillment_status')) {
                $table->dropIndex(['fulfillment_status']);
                $table->dropColumn('fulfillment_status');
            }
        });
    }
};
