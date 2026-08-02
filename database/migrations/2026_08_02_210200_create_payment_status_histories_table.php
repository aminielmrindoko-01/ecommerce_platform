<?php

/**
 * Audit trail for payment transaction status changes.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_status_histories')) {
            return;
        }

        Schema::create('payment_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_transaction_id')
                ->constrained('payment_transactions')
                ->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->string('reason', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['payment_transaction_id', 'created_at']);
            $table->index('actor_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_status_histories');
    }
};
