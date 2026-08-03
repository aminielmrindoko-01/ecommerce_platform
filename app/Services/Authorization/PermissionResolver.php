<?php

namespace App\Services\Authorization;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Central permission resolver (deny by default).
 *
 * Priority:
 * 1. Inactive → deny
 * 2. Materialize missing user_roles from legacy users.role (once)
 * 3. RBAC roles → permissions ONLY
 * 4. No roles → deny (fail closed)
 *
 * users.role never widens permissions beyond assigned RBAC roles.
 */
class PermissionResolver
{
    /** @var array<int, list<string>> */
    protected array $requestCache = [];

    public function permissionsFor(User $user): array
    {
        if (array_key_exists($user->id, $this->requestCache)) {
            return $this->requestCache[$user->id];
        }

        if ($user->is_active === false) {
            return $this->requestCache[$user->id] = [];
        }

        if (! $this->tablesReady()) {
            // Mid-migration fail-closed for elevated access; customers get none via empty.
            return $this->requestCache[$user->id] = [];
        }

        $cacheKey = $this->cacheKey($user->id);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $this->requestCache[$user->id] = $cached;
        }

        $roleNames = $user->roles()->pluck('name')->all();

        if ($roleNames === []) {
            $this->materializeLegacyRole($user);
            $roleNames = $user->roles()->pluck('name')->all();
        }

        if ($roleNames === []) {
            // If the RBAC catalog has not been seeded yet, allow a config-only
            // bridge for local/test bootstraps. Once roles exist in DB, missing
            // user_roles fail closed (legacy users.role cannot grant access).
            if (! Role::query()->exists()) {
                $permissions = $this->configBridgePermissions($user);
            } else {
                $permissions = [];
            }
        } else {
            $permissions = $this->expandRolePermissions($roleNames);
        }

        $permissions = array_values(array_unique($permissions));
        sort($permissions);

        Cache::put($cacheKey, $permissions, now()->addMinutes(5));

        return $this->requestCache[$user->id] = $permissions;
    }

    public function has(User $user, string $permission): bool
    {
        if ($permission === '' || $user->is_active === false) {
            return false;
        }

        return in_array($permission, $this->permissionsFor($user), true);
    }

    public function hasAny(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->has($user, (string) $permission)) {
                return true;
            }
        }

        return false;
    }

    public function forget(User $user): void
    {
        unset($this->requestCache[$user->id]);
        Cache::forget($this->cacheKey($user->id));
    }

    public function forgetAll(): void
    {
        $this->requestCache = [];
    }

    /**
     * Attach mapped RBAC role from legacy users.role when user_roles is empty.
     * Does not grant permissions directly — only creates RBAC rows.
     */
    public function materializeLegacyRole(User $user): void
    {
        if (! $this->tablesReady() || $user->roles()->exists()) {
            return;
        }

        $legacy = (string) ($user->role ?? 'customer');
        $mapped = (string) (config('authorization.legacy_role_map.'.$legacy) ?? 'customer');
        $role = Role::query()->where('name', $mapped)->first();

        if (! $role) {
            return;
        }

        $user->roles()->syncWithoutDetaching([$role->id]);
        $this->forget($user);
    }

    /**
     * Config-only bridge used exclusively when the roles table has zero rows.
     *
     * @return list<string>
     */
    protected function configBridgePermissions(User $user): array
    {
        $legacy = (string) ($user->role ?? 'customer');
        $mapped = (string) (config('authorization.legacy_role_map.'.$legacy) ?? 'customer');

        return $this->expandRolePermissions([$mapped]);
    }

    /**
     * @param  list<string>  $roleNames
     * @return list<string>
     */
    protected function expandRolePermissions(array $roleNames): array
    {
        $catalog = (array) config('authorization.permissions', []);
        $maps = (array) config('authorization.roles', []);
        $resolved = [];

        foreach ($roleNames as $name) {
            $map = $maps[$name] ?? null;
            if ($map === null) {
                $role = Role::query()->where('name', $name)->with('permissions')->first();
                if ($role) {
                    foreach ($role->permissions as $permission) {
                        $resolved[] = $permission->name;
                    }
                }

                continue;
            }

            if ($map === ['*'] || (count($map) === 1 && ($map[0] ?? null) === '*')) {
                $resolved = array_merge($resolved, $catalog);

                continue;
            }

            $resolved = array_merge($resolved, $map);
        }

        return $resolved;
    }

    protected function cacheKey(int $userId): string
    {
        return 'rbac.user.permissions.'.$userId;
    }

    protected function tablesReady(): bool
    {
        try {
            return Schema::hasTable('roles') && Schema::hasTable('user_roles') && Schema::hasTable('permissions');
        } catch (\Throwable) {
            return false;
        }
    }
}
