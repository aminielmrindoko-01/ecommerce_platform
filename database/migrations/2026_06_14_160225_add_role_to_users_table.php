<?php

/**
 * Duplicate empty stub — `users.role` already exists on create_users_table.
 * Retained for migration history only; applies no schema changes.
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
