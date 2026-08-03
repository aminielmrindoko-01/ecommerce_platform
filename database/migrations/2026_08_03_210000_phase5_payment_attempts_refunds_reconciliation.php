<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5: payment attempts, refunds, reconciliation, order inventory settlement state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_transactions', 'attempt_number')) {
                $table->unsignedInteger('attempt_number')->default(1)->after('order_id');
            }
            if (! Schema::hasColumn('payment_transactions', 'idempotency_key')) {
                $table->string('idempotency_key', 128)->nullable()->after('reference');
            }
            if (! Schema::hasColumn('payment_transactions', 'failure_code')) {
                $table->string('failure_code', 64)->nullable()->after('status');
            }
            if (! Schema::hasColumn('payment_transactions', 'failure_reason')) {
                $table->string('failure_reason', 500)->nullable()->after('failure_code');
            }
            if (! Schema::hasColumn('payment_transactions', 'initiated_at')) {
                $table->timestamp('initiated_at')->nullable()->after('paid_at');
            }
            if (! Schema::hasColumn('payment_transactions', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('initiated_at');
            }
            if (! Schema::hasColumn('payment_transactions', 'refunded_amount')) {
                $table->decimal('refunded_amount', 12, 2)->default(0)->after('amount');
            }
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            try {
                $table->unique('idempotency_key', 'payment_transactions_idempotency_key_unique');
            } catch (\Throwable) {
            }
            try {
                $table->index(['order_id', 'attempt_number'], 'payment_transactions_order_attempt_index');
            } catch (\Throwable) {
            }
        });

        if (! Schema::hasColumn('orders', 'inventory_state')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('inventory_state', 32)->default('none')->after('payment_status');
                $table->index('inventory_state');
            });
        }

        if (! Schema::hasTable('payment_refunds')) {
            Schema::create('payment_refunds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payment_transaction_id')->constrained('payment_transactions')->cascadeOnDelete();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('reference', 64)->unique();
                $table->decimal('amount', 12, 2);
                $table->string('currency', 3)->default('TZS');
                $table->string('status', 32)->default('requested');
                $table->string('provider_reference', 128)->nullable();
                $table->string('reason', 500)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['order_id', 'status']);
                $table->index('status');
            });
        }

        if (! Schema::hasTable('payment_reconciliations')) {
            Schema::create('payment_reconciliations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payment_transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->string('provider', 40)->nullable();
                $table->string('local_status', 32)->nullable();
                $table->string('provider_status', 64)->nullable();
                $table->string('severity', 16)->default('medium');
                $table->string('status', 32)->default('open'); // open|resolved|ignored
                $table->text('detail')->nullable();
                $table->json('context')->nullable();
                $table->timestamps();

                $table->index(['status', 'severity']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reconciliations');
        Schema::dropIfExists('payment_refunds');

        if (Schema::hasColumn('orders', 'inventory_state')) {
            Schema::table('orders', function (Blueprint $table) {
                try {
                    $table->dropIndex(['inventory_state']);
                } catch (\Throwable) {
                }
                $table->dropColumn('inventory_state');
            });
        }

        Schema::table('payment_transactions', function (Blueprint $table) {
            try {
                $table->dropUnique('payment_transactions_idempotency_key_unique');
            } catch (\Throwable) {
            }
            try {
                $table->dropIndex('payment_transactions_order_attempt_index');
            } catch (\Throwable) {
            }
            foreach ([
                'refunded_amount', 'completed_at', 'initiated_at', 'failure_reason',
                'failure_code', 'idempotency_key', 'attempt_number',
            ] as $col) {
                if (Schema::hasColumn('payment_transactions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
