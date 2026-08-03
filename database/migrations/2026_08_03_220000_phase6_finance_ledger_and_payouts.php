<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6: double-entry ledger, vendor entitlements, payouts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ledger_accounts')) {
            Schema::create('ledger_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('code', 64)->unique();
                $table->string('name');
                $table->string('type', 32); // asset|liability|revenue|expense
                $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
                $table->string('currency', 3)->default('TZS');
                $table->boolean('is_system')->default(true);
                $table->timestamps();

                $table->index(['type', 'vendor_id']);
            });
        }

        if (! Schema::hasTable('ledger_transactions')) {
            Schema::create('ledger_transactions', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 64)->unique();
                $table->string('idempotency_key', 128)->nullable()->unique();
                $table->string('type', 64); // payment_settlement|refund_reversal|payout|adjustment|reversal
                $table->string('currency', 3)->default('TZS');
                $table->string('description', 500)->nullable();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->foreignId('payment_transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
                $table->foreignId('payment_refund_id')->nullable()->constrained('payment_refunds')->nullOnDelete();
                $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('reverses_transaction_id')->nullable()->constrained('ledger_transactions')->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->timestamp('posted_at');
                $table->timestamps();

                $table->index(['type', 'posted_at']);
                $table->index('order_id');
            });
        }

        if (! Schema::hasTable('ledger_entries')) {
            Schema::create('ledger_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ledger_transaction_id')->constrained('ledger_transactions')->cascadeOnDelete();
                $table->foreignId('ledger_account_id')->constrained('ledger_accounts')->restrictOnDelete();
                $table->decimal('debit', 14, 2)->default(0);
                $table->decimal('credit', 14, 2)->default(0);
                $table->string('currency', 3)->default('TZS');
                $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
                $table->timestamps();

                $table->index(['ledger_account_id', 'vendor_id']);
            });
        }

        if (! Schema::hasTable('vendor_entitlements')) {
            Schema::create('vendor_entitlements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
                $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
                $table->foreignId('payment_transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
                $table->decimal('gross_amount', 14, 2);
                $table->decimal('commission_rate', 8, 4)->default(0); // e.g. 0.1000 = 10%
                $table->string('commission_type', 32)->default('percentage');
                $table->decimal('commission_amount', 14, 2);
                $table->decimal('net_amount', 14, 2);
                $table->decimal('refunded_gross', 14, 2)->default(0);
                $table->decimal('refunded_commission', 14, 2)->default(0);
                $table->decimal('refunded_net', 14, 2)->default(0);
                $table->string('currency', 3)->default('TZS');
                $table->string('status', 32)->default('earned'); // earned|partially_reversed|reversed
                $table->timestamp('available_at')->nullable(); // settlement hold
                $table->json('calculation_snapshot')->nullable();
                $table->timestamps();

                $table->unique('order_item_id');
                $table->index(['vendor_id', 'status']);
                $table->index('payment_transaction_id');
            });
        }

        if (! Schema::hasTable('vendor_payouts')) {
            Schema::create('vendor_payouts', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 64)->unique();
                $table->string('idempotency_key', 128)->nullable()->unique();
                $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('amount', 14, 2);
                $table->string('currency', 3)->default('TZS');
                $table->string('status', 32)->default('pending');
                $table->string('provider', 40)->default('stub');
                $table->string('provider_reference', 128)->nullable()->unique();
                $table->string('destination_token', 128)->nullable(); // tokenized destination only
                $table->string('failure_code', 64)->nullable();
                $table->string('failure_reason', 500)->nullable();
                $table->foreignId('ledger_transaction_id')->nullable()->constrained('ledger_transactions')->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['vendor_id', 'status']);
                $table->index('status');
            });
        }

        // Seed system chart of accounts (TZS).
        $now = now();
        $accounts = [
            ['code' => 'PLATFORM_CASH', 'name' => 'Platform Cash', 'type' => 'asset'],
            ['code' => 'VENDOR_PAYABLE', 'name' => 'Vendor Payable Clearing', 'type' => 'liability'],
            ['code' => 'PLATFORM_REVENUE', 'name' => 'Platform Commission Revenue', 'type' => 'revenue'],
            ['code' => 'REFUND_LIABILITY', 'name' => 'Refund Liability', 'type' => 'liability'],
            ['code' => 'PAYOUT_CLEARING', 'name' => 'Payout Clearing', 'type' => 'liability'],
        ];
        foreach ($accounts as $account) {
            $exists = DB::table('ledger_accounts')->where('code', $account['code'])->exists();
            if (! $exists) {
                DB::table('ledger_accounts')->insert([
                    'code' => $account['code'],
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'vendor_id' => null,
                    'currency' => 'TZS',
                    'is_system' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payouts');
        Schema::dropIfExists('vendor_entitlements');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('ledger_accounts');
    }
};
