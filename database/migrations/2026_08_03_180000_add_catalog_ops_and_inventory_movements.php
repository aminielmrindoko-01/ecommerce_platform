<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3: product lifecycle, category hierarchy, inventory movements.
 * Extends existing products.stock — does not invent a parallel stock column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'status')) {
                $table->string('status', 32)->default('published')->after('sku');
            }
            if (! Schema::hasColumn('products', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('products', 'reorder_level')) {
                $table->unsignedInteger('reorder_level')->default(5)->after('stock');
            }
            if (! Schema::hasColumn('products', 'reserved_quantity')) {
                $table->unsignedInteger('reserved_quantity')->default(0)->after('reorder_level');
            }
            if (! Schema::hasColumn('products', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('status', 'products_status_index');
        });

        // Unique SKU when provided (multiple NULLs allowed).
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            // SQLite: unique index via raw — Laravel unique() works.
            try {
                Schema::table('products', function (Blueprint $table) {
                    $table->unique('sku', 'products_sku_unique');
                });
            } catch (\Throwable) {
            }
        } else {
            try {
                Schema::table('products', function (Blueprint $table) {
                    $table->unique('sku', 'products_sku_unique');
                });
            } catch (\Throwable) {
            }
        }

        if (Schema::hasColumn('products', 'status')) {
            DB::table('products')->whereNull('status')->orWhere('status', '')->update([
                'status' => 'published',
            ]);
            DB::table('products')->whereNull('published_at')->update([
                'published_at' => DB::raw('created_at'),
            ]);
        }

        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'parent_id')) {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('categories')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('categories', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('sort_order');
            }
        });

        if (! Schema::hasTable('inventory_movements')) {
            Schema::create('inventory_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('type', 32);
                $table->integer('quantity_before');
                $table->integer('quantity_delta');
                $table->integer('quantity_after');
                $table->unsignedInteger('reserved_before')->default(0);
                $table->unsignedInteger('reserved_after')->default(0);
                $table->string('reason', 500);
                $table->string('reference_type', 64)->nullable();
                $table->string('reference_id', 64)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['product_id', 'created_at'], 'inventory_movements_product_created_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');

        if (Schema::hasColumn('categories', 'parent_id')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropConstrainedForeignId('parent_id');
            });
        }
        if (Schema::hasColumn('categories', 'is_active')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }

        Schema::table('products', function (Blueprint $table) {
            try {
                $table->dropUnique('products_sku_unique');
            } catch (\Throwable) {
            }
            try {
                $table->dropIndex('products_status_index');
            } catch (\Throwable) {
            }
        });

        foreach (['deleted_at', 'reserved_quantity', 'reorder_level', 'published_at', 'status'] as $col) {
            if (Schema::hasColumn('products', $col)) {
                Schema::table('products', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }
};
