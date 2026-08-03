<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds RBAC permission catalog, system roles, and bridges legacy users.role.
 */
class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = (array) config('authorization.permissions', []);
        $roleMaps = (array) config('authorization.roles', []);

        DB::transaction(function () use ($permissions, $roleMaps) {
            $permissionIds = [];

            foreach ($permissions as $name) {
                $group = Str::before($name, '.') ?: 'general';
                $permission = Permission::query()->updateOrCreate(
                    ['name' => $name],
                    [
                        'display_name' => Str::headline(str_replace('.', ' ', $name)),
                        'group' => $group,
                    ]
                );
                $permissionIds[$name] = $permission->id;
            }

            $displayNames = [
                'super_admin' => 'Super Admin',
                'admin' => 'Admin',
                'product_manager' => 'Product Manager',
                'inventory_manager' => 'Inventory Manager',
                'order_manager' => 'Order Manager',
                'customer_support' => 'Customer Support',
                'vendor_manager' => 'Vendor Manager',
                'marketing_manager' => 'Marketing Manager',
                'review_moderator' => 'Review Moderator',
                'finance_manager' => 'Finance Manager',
                'auditor' => 'Auditor',
                'vendor' => 'Vendor',
                'customer' => 'Customer',
            ];

            foreach ($roleMaps as $roleName => $perms) {
                $role = Role::query()->updateOrCreate(
                    ['name' => $roleName],
                    [
                        'display_name' => $displayNames[$roleName] ?? Str::headline($roleName),
                        'description' => 'System role: '.$roleName,
                        'is_system' => true,
                    ]
                );

                if ($perms === ['*'] || (count($perms) === 1 && ($perms[0] ?? null) === '*')) {
                    $ids = array_values($permissionIds);
                } else {
                    $ids = [];
                    foreach ($perms as $permName) {
                        if (isset($permissionIds[$permName])) {
                            $ids[] = $permissionIds[$permName];
                        }
                    }
                }

                $role->permissions()->sync($ids);
            }
        });

        // Bridge existing users that have no RBAC roles yet.
        $legacyMap = (array) config('authorization.legacy_role_map', []);
        $rolesByName = Role::query()->pluck('id', 'name');

        User::query()->orderBy('id')->chunkById(100, function ($users) use ($legacyMap, $rolesByName) {
            foreach ($users as $user) {
                if ($user->roles()->exists()) {
                    continue;
                }

                $mapped = $legacyMap[$user->role] ?? 'customer';
                $roleId = $rolesByName[$mapped] ?? null;
                if ($roleId) {
                    $user->roles()->syncWithoutDetaching([$roleId]);
                    app(PermissionResolver::class)->forget($user);
                }
            }
        });
    }
}
