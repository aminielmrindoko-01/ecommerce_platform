<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7: returns, disputes, chargebacks, settlement holds, commission configs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('financial_status', 32)->default('active')->after('status');
            $table->index('financial_status');
        });

        Schema::create('commission_configs', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 32); // platform|vendor|category
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('type', 32)->default('percentage'); // percentage|fixed
            $table->decimal('rate', 8, 4)->default(0.1000);
            $table->decimal('fixed_amount', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['scope', 'scope_id']);
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        // Seed platform default from config (env may not be loaded the same in all envs).
        DB::table('commission_configs')->insert([
            'scope' => 'platform',
            'scope_id' => null,
            'type' => 'percentage',
            'rate' => 0.1000,
            'fixed_amount' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('settlement_holds', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->string('reason_code', 40); // settlement_period|return|dispute|chargeback|manual
            $table->string('source_type', 40)->nullable();
            $table->string('source_id', 64)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('TZS');
            $table->string('status', 24)->default('active'); // active|released|consumed
            $table->text('reason')->nullable();
            $table->timestamp('held_at')->useCurrent();
            $table->timestamp('releases_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('released_by')->nullable();
            $table->timestamps();

            $table->foreign('vendor_id')->references('id')->on('vendors')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->foreign('order_item_id')->references('id')->on('order_items')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('released_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['vendor_id', 'status']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('vendor_id');
            $table->string('status', 32)->default('requested');
            $table->string('reason_code', 40)->nullable();
            $table->text('reason')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->boolean('restocked')->default(false);
            $table->unsignedBigInteger('payment_refund_id')->nullable();
            $table->unsignedBigInteger('settlement_hold_id')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('vendor_id')->references('id')->on('vendors')->cascadeOnDelete();
            $table->foreign('payment_refund_id')->references('id')->on('payment_refunds')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('received_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['customer_id', 'status']);
            $table->index(['vendor_id', 'status']);
            $table->index(['order_id', 'status']);
        });

        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('return_request_id');
            $table->unsignedBigInteger('order_item_id');
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 14, 2);
            $table->decimal('line_amount', 14, 2);
            $table->string('currency', 3)->default('TZS');
            $table->boolean('restockable')->nullable();
            $table->timestamps();

            $table->foreign('return_request_id')->references('id')->on('return_requests')->cascadeOnDelete();
            $table->foreign('order_item_id')->references('id')->on('order_items')->cascadeOnDelete();
            $table->foreign('vendor_id')->references('id')->on('vendors')->cascadeOnDelete();
            $table->unique(['return_request_id', 'order_item_id']);
        });

        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('vendor_id');
            $table->string('status', 40)->default('open');
            $table->string('subject', 160);
            $table->text('description')->nullable();
            $table->string('resolution_code', 40)->nullable();
            $table->text('resolution_notes')->nullable();
            $table->unsignedBigInteger('settlement_hold_id')->nullable();
            $table->unsignedBigInteger('return_request_id')->nullable();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('order_item_id')->references('id')->on('order_items')->nullOnDelete();
            $table->foreign('customer_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('vendor_id')->references('id')->on('vendors')->cascadeOnDelete();
            $table->foreign('return_request_id')->references('id')->on('return_requests')->nullOnDelete();
            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['customer_id', 'status']);
            $table->index(['vendor_id', 'status']);
        });

        Schema::create('dispute_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dispute_id');
            $table->unsignedBigInteger('author_id');
            $table->string('author_role', 32); // customer|vendor|support|system
            $table->text('body');
            $table->string('evidence_ref', 255)->nullable(); // opaque storage reference only
            $table->timestamps();

            $table->foreign('dispute_id')->references('id')->on('disputes')->cascadeOnDelete();
            $table->foreign('author_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('dispute_id');
        });

        Schema::create('chargebacks', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('payment_transaction_id')->nullable();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('TZS');
            $table->string('status', 32)->default('received');
            $table->string('provider', 64)->default('internal');
            $table->string('provider_reference', 128)->nullable();
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('settlement_hold_id')->nullable();
            $table->unsignedBigInteger('ledger_transaction_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('payment_transaction_id')->references('id')->on('payment_transactions')->nullOnDelete();
            $table->foreign('vendor_id')->references('id')->on('vendors')->nullOnDelete();
            $table->foreign('ledger_transaction_id')->references('id')->on('ledger_transactions')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['provider', 'provider_reference']);
            $table->index(['status', 'order_id']);
        });

        Schema::table('payment_refunds', function (Blueprint $table) {
            $table->unsignedBigInteger('return_request_id')->nullable()->after('order_id');
            $table->foreign('return_request_id')->references('id')->on('return_requests')->nullOnDelete();
        });

        Schema::table('return_requests', function (Blueprint $table) {
            $table->foreign('settlement_hold_id')->references('id')->on('settlement_holds')->nullOnDelete();
        });

        Schema::table('disputes', function (Blueprint $table) {
            $table->foreign('settlement_hold_id')->references('id')->on('settlement_holds')->nullOnDelete();
        });

        Schema::table('chargebacks', function (Blueprint $table) {
            $table->foreign('settlement_hold_id')->references('id')->on('settlement_holds')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_refunds', function (Blueprint $table) {
            $table->dropForeign(['return_request_id']);
            $table->dropColumn('return_request_id');
        });

        Schema::dropIfExists('chargebacks');
        Schema::dropIfExists('dispute_messages');
        Schema::dropIfExists('disputes');
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('return_requests');
        Schema::dropIfExists('settlement_holds');
        Schema::dropIfExists('commission_configs');

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropIndex(['financial_status']);
            $table->dropColumn('financial_status');
        });
    }
};
