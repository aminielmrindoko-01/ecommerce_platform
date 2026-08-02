<?php

/**
 * Add remember_token so "remember me" login sessions persist correctly.
 * email_verified_at reserved for future MustVerifyEmail support.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add auth columns expected by Laravel's Authenticatable defaults.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('email');
            }

            if (! Schema::hasColumn('users', 'remember_token')) {
                $table->rememberToken()->after('password');
            }
        });
    }

    /**
     * Drop the added auth columns.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'remember_token')) {
                $table->dropColumn('remember_token');
            }

            if (Schema::hasColumn('users', 'email_verified_at')) {
                $table->dropColumn('email_verified_at');
            }
        });
    }
};
