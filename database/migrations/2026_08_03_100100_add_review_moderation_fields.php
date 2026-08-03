<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Review moderation workflow fields (Phase RBAC).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('reviews', 'status')) {
                $table->string('status', 20)->default('APPROVED')->after('body')->index();
            }
            if (! Schema::hasColumn('reviews', 'vendor_id')) {
                $table->foreignId('vendor_id')->nullable()->after('product_id')->constrained('vendors')->nullOnDelete();
            }
            if (! Schema::hasColumn('reviews', 'order_id')) {
                $table->foreignId('order_id')->nullable()->after('user_id')->constrained('orders')->nullOnDelete();
            }
            if (! Schema::hasColumn('reviews', 'verified_purchase')) {
                $table->boolean('verified_purchase')->default(false)->after('status');
            }
            if (! Schema::hasColumn('reviews', 'moderated_at')) {
                $table->timestamp('moderated_at')->nullable()->after('verified_purchase');
            }
            if (! Schema::hasColumn('reviews', 'moderated_by')) {
                $table->foreignId('moderated_by')->nullable()->after('moderated_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('reviews', 'moderation_reason')) {
                $table->string('moderation_reason')->nullable()->after('moderated_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            foreach (['moderation_reason', 'moderated_by', 'moderated_at', 'verified_purchase', 'status', 'order_id', 'vendor_id'] as $col) {
                if (Schema::hasColumn('reviews', $col)) {
                    if (in_array($col, ['moderated_by', 'order_id', 'vendor_id'], true)) {
                        $table->dropConstrainedForeignId($col);
                    } else {
                        $table->dropColumn($col);
                    }
                }
            }
        });
    }
};
