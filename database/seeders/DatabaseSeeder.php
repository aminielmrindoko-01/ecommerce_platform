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
                'phone' => '+255700000001',
            ]
        )->forceFill(['role' => 'admin', 'is_active' => true])->save();

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
