<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds demo admin/customer/vendor accounts, then marketplace catalog data.
 *
 * Local/dev only.
 *
 * Super Admin demo (also available alone via DemoSuperAdminSeeder):
 *   admin@market.com / password123
 *
 * Other demo users default password: password
 *
 * WARNING: MarketplaceSeeder clears catalog demo rows (products/vendors/…).
 * Prefer `php artisan db:seed --class=DemoSuperAdminSeeder` on databases
 * that already contain data you must keep.
 *
 * @package Database\Seeders
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Upsert known demo users and invoke MarketplaceSeeder.
     */
    public function run(): void
    {
        // Safe Super Admin upsert + RBAC assignment (does not wipe catalog).
        $this->call(DemoSuperAdminSeeder::class);

        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
                'phone' => '+255700000002',
            ]
        )->forceFill(['role' => 'customer', 'is_active' => true])->save();

        User::updateOrCreate(
            ['email' => 'seller@example.com'],
            [
                'name' => 'Seller Demo',
                'password' => 'password',
                'phone' => '+255700000003',
            ]
        )->forceFill(['role' => 'vendor', 'is_active' => true])->save();

        $this->call([
            RbacSeeder::class,
            MarketplaceSeeder::class,
        ]);
    }
}
