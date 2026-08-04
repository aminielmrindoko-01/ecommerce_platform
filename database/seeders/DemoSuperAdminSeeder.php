<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Local/dev demo Super Admin upsert — SAFE for existing databases.
 *
 * Creates/updates admin@market.com and assigns RBAC super_admin via user_roles.
 * Does NOT wipe marketplace catalog data (unlike MarketplaceSeeder).
 *
 * Password is hashed by the User model's `hashed` cast.
 *
 * Usage:
 *   php artisan db:seed --class=DemoSuperAdminSeeder
 */
class DemoSuperAdminSeeder extends Seeder
{
    public const EMAIL = 'admin@market.com';

    public const NAME = 'Market Super Admin';

    /**
     * Development-only demo password. Never use in production.
     */
    public const DEV_PASSWORD = 'password123';

    public function run(): void
    {
        // Ensure RBAC catalog exists (roles/permissions only — no user wipe).
        if (! Role::query()->where('name', 'super_admin')->exists()) {
            $this->call(RbacSeeder::class);
        }

        DB::transaction(function () {
            $user = User::query()->updateOrCreate(
                ['email' => self::EMAIL],
                [
                    'name' => self::NAME,
                    'password' => self::DEV_PASSWORD,
                    'phone' => '+255700000001',
                ]
            );

            $user->forceFill([
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => $user->email_verified_at ?: now(),
            ])->save();

            $existingOtherSa = User::query()
                ->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))
                ->where('email', '!=', self::EMAIL)
                ->exists();

            // Never create a second Super Admin when one already exists.
            $roleName = $existingOtherSa ? 'admin' : 'super_admin';
            $role = Role::query()->where('name', $roleName)->firstOrFail();
            $user->roles()->sync([$role->id]);
            app(PermissionResolver::class)->forget($user->fresh());
        });
    }
}
