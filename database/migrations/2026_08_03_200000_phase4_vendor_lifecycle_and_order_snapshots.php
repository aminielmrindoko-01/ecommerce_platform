<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4: vendor lifecycle status, order-item snapshots, expandable order statuses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (! Schema::hasColumn('vendors', 'status')) {
                $table->string('status', 32)->default('approved')->after('is_verified');
            }
            if (! Schema::hasColumn('vendors', 'application_notes')) {
                $table->text('application_notes')->nullable()->after('status');
            }
            if (! Schema::hasColumn('vendors', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('application_notes');
            }
            if (! Schema::hasColumn('vendors', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')
                    ->constrained('users')->nullOnDelete();
            }
        });

        // Backfill vendor status from is_verified.
        if (Schema::hasColumn('vendors', 'status')) {
            DB::table('vendors')->where('is_verified', true)->update(['status' => 'approved']);
            DB::table('vendors')->where('is_verified', false)->where('status', 'approved')
                ->update(['status' => 'pending']);
        }

        Schema::table('vendors', function (Blueprint $table) {
            $table->index('status', 'vendors_status_index');
        });

        // Expand orders.status beyond original enum (pending/paid/shipped/completed).
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY status VARCHAR(32) NOT NULL DEFAULT 'pending'");
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE orders ALTER COLUMN status TYPE VARCHAR(32)');
        } elseif ($driver === 'sqlite') {
            // SQLite enum() is a CHECK constraint — rebuild orders.status as free string.
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'status_expanded')) {
                    $table->string('status_expanded', 32)->default('pending');
                }
            });

            DB::table('orders')->orderBy('id')->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('orders')->where('id', $row->id)->update([
                        'status_expanded' => $row->status ?: 'pending',
                    ]);
                }
            });

            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('status');
            });

            Schema::table('orders', function (Blueprint $table) {
                $table->renameColumn('status_expanded', 'status');
            });
        }
        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'vendor_id')) {
                $table->foreignId('vendor_id')->nullable()->after('product_id')
                    ->constrained('vendors')->nullOnDelete();
            }
            if (! Schema::hasColumn('order_items', 'product_name')) {
                $table->string('product_name')->nullable()->after('vendor_id');
            }
            if (! Schema::hasColumn('order_items', 'product_sku')) {
                $table->string('product_sku', 64)->nullable()->after('product_name');
            }
            if (! Schema::hasColumn('order_items', 'vendor_store_name')) {
                $table->string('vendor_store_name')->nullable()->after('product_sku');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index('vendor_id', 'order_items_vendor_id_index');
        });

        if (! Schema::hasColumn('orders', 'currency')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('currency', 3)->default('TZS')->after('total_price');
            });
        }

        // Backfill snapshots from live products where possible.
        if (Schema::hasColumn('order_items', 'vendor_id')) {
            $items = DB::table('order_items')
                ->join('products', 'products.id', '=', 'order_items.product_id')
                ->leftJoin('vendors', 'vendors.id', '=', 'products.vendor_id')
                ->select(
                    'order_items.id',
                    'products.vendor_id',
                    'products.name as product_name',
                    'products.sku as product_sku',
                    'vendors.store_name as vendor_store_name'
                )
                ->get();

            foreach ($items as $row) {
                DB::table('order_items')->where('id', $row->id)->update([
                    'vendor_id' => $row->vendor_id,
                    'product_name' => $row->product_name,
                    'product_sku' => $row->product_sku,
                    'vendor_store_name' => $row->vendor_store_name,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            try {
                $table->dropIndex('order_items_vendor_id_index');
            } catch (\Throwable) {
            }
            foreach (['vendor_store_name', 'product_sku', 'product_name', 'vendor_id'] as $col) {
                if (Schema::hasColumn('order_items', $col)) {
                    if ($col === 'vendor_id') {
                        $table->dropConstrainedForeignId('vendor_id');
                    } else {
                        $table->dropColumn($col);
                    }
                }
            }
        });

        Schema::table('vendors', function (Blueprint $table) {
            try {
                $table->dropIndex('vendors_status_index');
            } catch (\Throwable) {
            }
            if (Schema::hasColumn('vendors', 'reviewed_by')) {
                $table->dropConstrainedForeignId('reviewed_by');
            }
            foreach (['reviewed_at', 'application_notes', 'status'] as $col) {
                if (Schema::hasColumn('vendors', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
