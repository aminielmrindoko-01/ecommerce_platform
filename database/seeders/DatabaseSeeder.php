<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds demo admin/customer/vendor accounts, then marketplace catalog data.
 *
 * Default password for all demo users: `password` (local/dev only).
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
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => 'password',
                'role' => 'admin',
                'phone' => '+255700000001',
            ]
        );

        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
                'role' => 'customer',
                'phone' => '+255700000002',
            ]
        );

        User::updateOrCreate(
            ['email' => 'seller@example.com'],
            [
                'name' => 'Seller Demo',
                'password' => 'password',
                'role' => 'vendor',
                'phone' => '+255700000003',
            ]
        );

        $this->call([
            MarketplaceSeeder::class,
        ]);
    }
}
