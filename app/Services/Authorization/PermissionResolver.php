<?php

namespace App\Services\Authorization;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Central permission resolver (deny by default).
 *
 * Resolution order:
 * 1. Inactive users → no permissions
 * 2. Assigned RBAC roles → union of role permissions (* expands to catalog)
 * 3. Legacy users.role bridge when no RBAC roles assigned yet
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

        if (! $this->tablesReady()) {
            return $this->requestCache[$user->id] = $this->legacyPermissions($user);
        }

        if ($user->is_active === false) {
            return $this->requestCache[$user->id] = [];
        }

        $cacheKey = $this->cacheKey($user->id);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $this->requestCache[$user->id] = $cached;
        }

        $roleNames = $user->roles()->pluck('name')->all();

        if ($roleNames === []) {
            $permissions = $this->legacyPermissions($user);
        } else {
            $permissions = $this->expandRolePermissions($roleNames);
        }

        $permissions = array_values(array_unique($permissions));
        sort($permissions);

        Cache::put($cacheKey, $permissions, now()->addMinutes(10));

        return $this->requestCache[$user->id] = $permissions;
    }

    public function has(User $user, string $permission): bool
    {
        if ($permission === '') {
            return false;
        }

        if ($user->is_active === false) {
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
                // DB-only custom role: load from role_permissions
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

    /**
     * @return list<string>
     */
    protected function legacyPermissions(User $user): array
    {
        if ($user->is_active === false) {
            return [];
        }

        $legacy = (string) ($user->role ?? 'customer');
        $mapped = (string) (config('authorization.legacy_role_map.'.$legacy) ?? 'customer');
        $map = (array) (config('authorization.roles.'.$mapped) ?? []);

        if ($map === ['*'] || (count($map) === 1 && ($map[0] ?? null) === '*')) {
            return array_values((array) config('authorization.permissions', []));
        }

        return array_values($map);
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
