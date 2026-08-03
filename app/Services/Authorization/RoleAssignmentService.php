<?php

namespace App\Services\Authorization;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Assign / sync RBAC roles with privilege escalation protections.
 */
class RoleAssignmentService
{
    public function __construct(
        protected PermissionResolver $permissions,
        protected AuditLogger $audit,
    ) {}

    /**
     * Replace a user's RBAC roles with the given role names.
     *
     * @param  list<string>  $roleNames
     */
    public function syncRoles(User $actor, User $target, array $roleNames, ?string $legacyRole = null): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('user_roles')) {
            if ($legacyRole !== null) {
                $target->forceFill(['role' => $legacyRole])->save();
            }

            return;
        }

        $roleNames = array_values(array_unique(array_filter(array_map('strval', $roleNames))));

        foreach ($roleNames as $name) {
            if (in_array($name, config('authorization.protected_roles', []), true)
                && ! $actor->hasPermission('permissions.assign')
                && ! $this->actorIsSuperAdmin($actor)) {
                $this->audit->security('PRIVILEGE_ESCALATION_ATTEMPT', $actor, 'high', [
                    'target_user_id' => $target->id,
                    'attempted_role' => $name,
                ]);
                throw new InvalidArgumentException('You are not allowed to assign protected roles.');
            }
        }

        if ($this->targetIsLastSuperAdmin($target) && ! in_array('super_admin', $roleNames, true)) {
            throw new InvalidArgumentException('Cannot remove the last Super Admin.');
        }

        $roles = Role::query()->whereIn('name', $roleNames)->get();
        if ($roles->count() !== count($roleNames)) {
            // RBAC catalog not seeded yet — apply legacy marketplace role only.
            if ($legacyRole !== null) {
                $before = $target->role;
                $target->forceFill(['role' => $legacyRole])->save();
                $this->permissions->forget($target);
                $this->audit->log(
                    action: 'USER_ROLE_CHANGED',
                    actor: $actor,
                    resourceType: 'user',
                    resourceId: $target->id,
                    oldValues: ['legacy_role' => $before],
                    newValues: ['legacy_role' => $legacyRole, 'rbac' => 'skipped_catalog_missing'],
                    category: 'security',
                );

                return;
            }

            throw new InvalidArgumentException('One or more roles are invalid.');
        }

        $before = $target->roles()->pluck('name')->all();

        DB::transaction(function () use ($target, $roles, $legacyRole) {
            $target->roles()->sync($roles->pluck('id')->all());

            if ($legacyRole !== null) {
                $target->forceFill(['role' => $legacyRole])->save();
            } else {
                // Keep legacy column aligned for marketplace middleware.
                $target->forceFill([
                    'role' => $this->inferLegacyRole($roles->pluck('name')->all()),
                ])->save();
            }
        });

        $this->permissions->forget($target);

        $this->audit->log(
            action: 'USER_ROLE_CHANGED',
            actor: $actor,
            resourceType: 'user',
            resourceId: $target->id,
            oldValues: ['roles' => $before, 'legacy_role' => $target->getOriginal('role')],
            newValues: ['roles' => $roles->pluck('name')->all(), 'legacy_role' => $target->fresh()->role],
            category: 'security',
        );
    }

    /**
     * Ensure a user has the RBAC role matching their legacy users.role.
     */
    public function ensureLegacyBridge(User $user): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('user_roles')) {
            return;
        }

        if ($user->roles()->exists()) {
            return;
        }

        $mapped = (string) (config('authorization.legacy_role_map.'.$user->role) ?? 'customer');
        $role = Role::query()->where('name', $mapped)->first();
        if (! $role) {
            return;
        }

        $user->roles()->syncWithoutDetaching([$role->id]);
        $this->permissions->forget($user);
    }

    protected function actorIsSuperAdmin(User $actor): bool
    {
        return in_array('super_admin', $actor->roleNames(), true);
    }

    protected function targetIsLastSuperAdmin(User $target): bool
    {
        $isSuper = $target->roles()->where('name', 'super_admin')->exists();
        if (! $isSuper) {
            return false;
        }

        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))
            ->where('id', '!=', $target->id)
            ->doesntExist();
    }

    /**
     * @param  list<string>  $roleNames
     */
    protected function inferLegacyRole(array $roleNames): string
    {
        if (in_array('vendor', $roleNames, true) && count($roleNames) === 1) {
            return 'vendor';
        }

        if (in_array('customer', $roleNames, true) && count($roleNames) === 1) {
            return 'customer';
        }

        // Any platform staff role maps to legacy admin for storefront admin middleware compatibility.
        $staff = array_diff($roleNames, ['vendor', 'customer']);
        if ($staff !== []) {
            return 'admin';
        }

        return 'customer';
    }
}
