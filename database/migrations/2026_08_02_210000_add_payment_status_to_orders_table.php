<?php

/**
 * Dedicated payment lifecycle on orders (separate from orders.status).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'payment_status')) {
                $table->string('payment_status', 32)
                    ->default('pending')
                    ->after('status');

                $table->index('payment_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'payment_status')) {
                $table->dropIndex(['payment_status']);
                $table->dropColumn('payment_status');
            }
        });
    }
};
