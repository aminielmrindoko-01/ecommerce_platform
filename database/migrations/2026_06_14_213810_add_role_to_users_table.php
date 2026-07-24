<?php

/**
 * Stub migration: no schema changes applied.
 *
 * The `users.role` enum is created in 0001_01_01_000000_create_users_table.
 * This later-dated file is an empty stub retained for migration history only.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Intentionally empty — no columns added.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // No-op: role column not defined here (see class docblock).
        });
    }

    /**
     * Intentionally empty.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // No-op.
        });
    }
};
