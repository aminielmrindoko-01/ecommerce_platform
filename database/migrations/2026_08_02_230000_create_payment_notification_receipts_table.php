<?php

/**
 * Replay/audit receipts for provider payment notifications (Phase 8B).
 * Does not replace PaymentService state-machine protection.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_notification_receipts')) {
            return;
        }

        Schema::create('payment_notification_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->string('notification_key', 128)->unique();
            $table->string('merchant_reference', 64)->nullable()->index();
            $table->string('tracking_id', 128)->nullable()->index();
            $table->string('notification_type', 40)->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->string('processing_status', 32);
            $table->string('failure_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['provider', 'processing_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_notification_receipts');
    }
};
