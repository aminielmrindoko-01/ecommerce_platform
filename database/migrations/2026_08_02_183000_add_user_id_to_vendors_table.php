<?php

/**
 * Link vendor stores to login accounts (1:1).
 *
 * Safe evolution: add nullable user_id → backfill when possible →
 * unique index for one store per user. Does not alter the original
 * create_vendors_table migration (MySQL order fix preserved).
 *
 * Fresh installs rely on seeders to set user_id after users/vendors exist.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add vendors.user_id with FK + unique ownership.
     */
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (! Schema::hasColumn('vendors', 'user_id')) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('id')
                    ->constrained()
                    ->nullOnDelete();
            }
        });

        // Backfill for existing databases that already have users + vendors.
        $vendors = DB::table('vendors')->whereNull('user_id')->orderBy('id')->get();

        foreach ($vendors as $vendor) {
            if (empty($vendor->email)) {
                continue;
            }

            $userId = DB::table('users')
                ->where('email', $vendor->email)
                ->where('role', 'vendor')
                ->value('id');

            if ($userId && ! DB::table('vendors')->where('user_id', $userId)->exists()) {
                DB::table('vendors')->where('id', $vendor->id)->update(['user_id' => $userId]);
            }
        }

        $sellerId = DB::table('users')->where('email', 'seller@example.com')->value('id');
        if ($sellerId && ! DB::table('vendors')->where('user_id', $sellerId)->exists()) {
            $techHavenId = DB::table('vendors')
                ->whereNull('user_id')
                ->where('store_name', 'Tech Haven')
                ->value('id');

            if ($techHavenId) {
                DB::table('vendors')->where('id', $techHavenId)->update(['user_id' => $sellerId]);
            }
        }

        $indexes = Schema::getIndexes('vendors');
        $hasUniqueUserId = collect($indexes)->contains(function (array $index) {
            return ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['user_id'];
        });

        if (! $hasUniqueUserId) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->unique('user_id');
            });
        }
    }

    /**
     * Drop ownership column and constraints.
     */
    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (Schema::hasColumn('vendors', 'user_id')) {
                $table->dropUnique(['user_id']);
                $table->dropConstrainedForeignId('user_id');
            }
        });
    }
};
