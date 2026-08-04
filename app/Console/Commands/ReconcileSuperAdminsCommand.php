<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\RoleAssignmentService;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

/**
 * Infrastructure repair: keep one Super Admin and demote other Super Admins to RBAC admin.
 *
 * Example:
 *   php artisan admin:reconcile-super-admins --keep=admin@gmail.com --force
 *
 * Does not wipe data. Uses RoleAssignmentService. Bootstrap lock remains for create-super-admin.
 */
class ReconcileSuperAdminsCommand extends Command
{
    protected $signature = 'admin:reconcile-super-admins
                            {--keep= : Email of the user who must remain the only Super Admin}
                            {--demote-to=admin : RBAC role for demoted former Super Admins}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Ensure exactly one Super Admin (by email) and demote other Super Admins to admin';

    public function handle(RoleAssignmentService $roles): int
    {
        $keepEmail = strtolower(trim((string) $this->option('keep')));
        $demoteTo = trim((string) $this->option('demote-to') ?: 'admin');

        if ($keepEmail === '' || ! filter_var($keepEmail, FILTER_VALIDATE_EMAIL)) {
            $this->error('Provide a valid --keep=email@example.com');

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

        /** @var User|null $keep */
        $keep = User::query()->whereRaw('LOWER(email) = ?', [$keepEmail])->first();
        if (! $keep) {
            $this->error("Keep user [{$keepEmail}] was not found. No changes made.");

            return self::FAILURE;
        }

        if (! $keep->isActiveAccount()) {
            $this->error('Keep user is inactive. Activate the account before reconciling.');

            return self::FAILURE;
        }

        $others = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))
            ->where('id', '!=', $keep->id)
            ->orderBy('id')
            ->get();

        $this->table(['Field', 'Value'], [
            ['Keep Super Admin', $keep->email.' (ID '.$keep->id.')'],
            ['Keep currently SA', $keep->isSuperAdmin() ? 'yes' : 'no'],
            ['Other Super Admins', (string) $others->count()],
            ['Demote to', $demoteTo],
        ]);

        foreach ($others as $other) {
            $this->line(" - will demote: {$other->email} (ID {$other->id})");
        }

        if (! $this->option('force') && ! $this->confirm('Apply Super Admin reconciliation?', false)) {
            $this->comment('Aborted. No changes made.');

            return self::FAILURE;
        }

        try {
            if (! $keep->roles()->where('name', 'super_admin')->exists()) {
                $existingSa = User::query()
                    ->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))
                    ->where('id', '!=', $keep->id)
                    ->first();

                if ($existingSa) {
                    $roles->syncRoles($existingSa, $keep, ['super_admin'], 'admin');
                    $this->info("Promoted {$keep->email} to super_admin (actor {$existingSa->email})");
                } elseif (! $roles->superAdminExists()) {
                    $roles->bootstrapFirstSuperAdmin($keep->fresh());
                    $this->info("Bootstrapped super_admin onto {$keep->email}");
                } else {
                    $this->error('Unable to promote keep user under current Super Admin constraints.');

                    return self::FAILURE;
                }
            }

            $keep = $keep->fresh();
            if (! $keep->isSuperAdmin()) {
                $this->error('Keep user is not Super Admin after promotion attempt.');

                return self::FAILURE;
            }

            $others = User::query()
                ->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))
                ->where('id', '!=', $keep->id)
                ->orderBy('id')
                ->get();

            foreach ($others as $other) {
                $roles->syncRoles($keep, $other, [$demoteTo], 'admin');
                $this->info("Demoted {$other->email} → {$demoteTo}");
            }
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('Reconciliation failed: '.$e->getMessage());
            report($e);

            return self::FAILURE;
        }

        $remaining = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))
            ->orderBy('id')
            ->get(['id', 'email']);

        $this->newLine();
        $this->info('Super Admins now:');
        foreach ($remaining as $row) {
            $this->line(" - #{$row->id} {$row->email}");
        }

        if ($remaining->count() !== 1 || strtolower((string) $remaining->first()->email) !== $keepEmail) {
            $this->warn('Unexpected Super Admin set after reconciliation — review manually.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
