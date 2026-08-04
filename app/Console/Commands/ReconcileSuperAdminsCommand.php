<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\RoleAssignmentService;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

/**
 * Infrastructure repair: keep exactly one Super Admin and demote others to RBAC admin.
 *
 * Example:
 *   php artisan admin:reconcile-super-admins --keep=admin@gmail.com --force
 *
 * Does not delete users, roles, or permissions. Uses RoleAssignmentService so
 * privilege checks and audit logging stay consistent with the admin UI path.
 * The bootstrap command (admin:create-super-admin) remains locked once any SA exists.
 */
class ReconcileSuperAdminsCommand extends Command
{
    protected $signature = 'admin:reconcile-super-admins
                            {--keep= : Email of the user who must remain the only Super Admin}
                            {--demote-to=admin : RBAC role for demoted former Super Admins (default: admin)}
                            {--force : Required. Apply destructive role changes without confirmation}';

    protected $description = 'Ensure exactly one Super Admin (by email) and demote other Super Admins to admin';

    public function handle(RoleAssignmentService $roles): int
    {
        $keepEmail = strtolower(trim((string) $this->option('keep')));
        $demoteTo = trim((string) $this->option('demote-to') ?: 'admin');

        if ($keepEmail === '' || ! filter_var($keepEmail, FILTER_VALIDATE_EMAIL)) {
            $this->error('Provide a valid --keep=email@example.com');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->error('Refusing to change roles without --force.');
            $this->line('Example: php artisan admin:reconcile-super-admins --keep='.$keepEmail.' --force');

            return self::FAILURE;
        }

        if ($demoteTo === 'super_admin') {
            $this->error('--demote-to cannot be super_admin.');

            return self::FAILURE;
        }

        if (! Role::query()->where('name', $demoteTo)->exists()) {
            $this->error("RBAC role [{$demoteTo}] does not exist. Seed the RBAC catalog first.");

            return self::FAILURE;
        }

        if (! Role::query()->where('name', 'super_admin')->exists()) {
            $this->error('RBAC role [super_admin] does not exist. Seed the RBAC catalog first.');

            return self::FAILURE;
        }

        /** @var User|null $keep */
        $keep = User::query()->whereRaw('LOWER(email) = ?', [$keepEmail])->first();
        if (! $keep) {
            $this->error("Keep user [{$keepEmail}] was not found. No changes made.");

            return self::FAILURE;
        }

        if (! $keep->isActiveAccount()) {
            $this->error('Keep user is inactive. Activate the account before reconciling. No changes made.');

            return self::FAILURE;
        }

        $others = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))
            ->where('id', '!=', $keep->id)
            ->orderBy('id')
            ->get();

        $this->info('Planned reconciliation');
        $this->table(['Field', 'Value'], [
            ['Keep Super Admin', $keep->email.' (ID '.$keep->id.')'],
            ['Keep currently SA', $keep->isSuperAdmin() ? 'yes' : 'no'],
            ['Keep current roles', implode(', ', $keep->roleNames()) ?: '(none)'],
            ['Keep legacy role', (string) $keep->role],
            ['Other Super Admins', (string) $others->count()],
            ['Demote to', $demoteTo],
        ]);

        foreach ($others as $other) {
            $this->line(sprintf(
                ' - demote: %s (ID %d) roles=[%s] legacy=%s',
                $other->email,
                $other->id,
                implode(', ', $other->roleNames()) ?: 'none',
                (string) $other->role
            ));
        }

        $keepRoleNames = $keep->roleNames();
        sort($keepRoleNames);
        if ($others->isEmpty() && $keep->isSuperAdmin() && $keepRoleNames === ['super_admin']) {
            $this->info('Already reconciled: exactly one Super Admin with only super_admin role.');

            return $this->printFinalSummary($keepEmail);
        }

        try {
            // 1) Ensure keep holds super_admin BEFORE demoting anyone (never end with zero SAs).
            if (! $keep->roles()->where('name', 'super_admin')->exists()) {
                $existingSa = User::query()
                    ->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))
                    ->where('id', '!=', $keep->id)
                    ->first();

                if ($existingSa) {
                    // Actor must already be SA so RoleAssignmentService allows protected role assign.
                    $roles->syncRoles($existingSa, $keep, ['super_admin'], 'admin');
                    $this->info("Promoted {$keep->email} → super_admin (actor {$existingSa->email})");
                } elseif (! $roles->superAdminExists()) {
                    $roles->bootstrapFirstSuperAdmin($keep->fresh());
                    $this->info("Bootstrapped super_admin onto {$keep->email}");
                } else {
                    $this->error('Unable to promote keep user under current Super Admin constraints. No demotions applied.');

                    return self::FAILURE;
                }
            }

            $keep = $keep->fresh();
            if (! $keep->isSuperAdmin()) {
                $this->error('Keep user is not Super Admin after promotion attempt. Aborting before demotions.');

                return self::FAILURE;
            }

            // 2) Keep user retains ONLY the super_admin RBAC role; legacy stays admin.
            $keepRoles = $keep->roleNames();
            sort($keepRoles);
            if ($keepRoles !== ['super_admin'] || $keep->role !== 'admin') {
                $roles->syncRoles($keep, $keep, ['super_admin'], 'admin');
                $this->info("Normalized {$keep->email} → roles=[super_admin], legacy=admin");
            }

            // 3) Demote every other Super Admin to normal admin (never delete users).
            $others = User::query()
                ->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))
                ->where('id', '!=', $keep->id)
                ->orderBy('id')
                ->get();

            foreach ($others as $other) {
                // Former Super Admins become normal platform admins:
                // RBAC demote-to (default admin) + legacy admin bridge.
                $roles->syncRoles($keep, $other, [$demoteTo], 'admin');
                $fresh = $other->fresh();
                $this->info(sprintf(
                    'Demoted %s → rbac=[%s], legacy=%s, isSuperAdmin=%s',
                    $fresh->email,
                    implode(', ', $fresh->roleNames()),
                    (string) $fresh->role,
                    $fresh->isSuperAdmin() ? 'true' : 'false'
                ));
            }
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('Reconciliation failed: '.$e->getMessage());
            report($e);

            return self::FAILURE;
        }

        return $this->printFinalSummary($keepEmail);
    }

    private function printFinalSummary(string $keepEmail): int
    {
        $remaining = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))
            ->with('roles')
            ->orderBy('id')
            ->get();

        $this->newLine();
        $this->info('Affected / current Super Admin set:');
        foreach ($remaining as $row) {
            $this->line(sprintf(
                ' - #%d %s roles=[%s] isSuperAdmin=%s',
                $row->id,
                $row->email,
                implode(', ', $row->roles->pluck('name')->all()),
                $row->isSuperAdmin() ? 'true' : 'false'
            ));
        }

        if ($remaining->count() !== 1) {
            $this->error('Expected exactly one Super Admin after reconciliation; found '.$remaining->count().'.');

            return self::FAILURE;
        }

        $only = $remaining->first();
        if (strtolower((string) $only->email) !== $keepEmail) {
            $this->error('The remaining Super Admin is not the --keep user. Review manually.');

            return self::FAILURE;
        }

        $onlyRoles = $only->roleNames();
        sort($onlyRoles);
        if ($onlyRoles !== ['super_admin']) {
            $this->error('Keep Super Admin must hold only the super_admin role.');

            return self::FAILURE;
        }

        $this->info('OK: exactly one Super Admin ('.$only->email.').');

        return self::SUCCESS;
    }
}
