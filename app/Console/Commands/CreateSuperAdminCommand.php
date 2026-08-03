<?php

namespace App\Console\Commands;

use App\Services\Authorization\SuperAdminBootstrapService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * Secure first Super Admin bootstrap.
 *
 * Usage: php artisan admin:create-super-admin
 *
 * Locked once any RBAC super_admin exists. No HTTP endpoint. No hard-coded credentials.
 */
class CreateSuperAdminCommand extends Command
{
    protected $signature = 'admin:create-super-admin
                            {--name= : Operator display name}
                            {--email= : Operator email address}';

    protected $description = 'Bootstrap the first Super Admin (initial setup only; locked after first success)';

    public function handle(SuperAdminBootstrapService $bootstrap): int
    {
        $this->info('SANA Market — Super Admin bootstrap');
        $this->line('This creates a high-privilege account. Normal login + MFA still apply afterward.');
        $this->newLine();

        if ($bootstrap->isLocked()) {
            $this->error('Bootstrap locked: a Super Admin already exists.');
            $this->line('Use an existing Super Admin to assign least-privilege roles. No backdoor is available.');

            return self::FAILURE;
        }

        if ($this->laravel->environment('production')) {
            $this->warn('ENVIRONMENT: production');
            $this->warn('THIS CREATES A HIGH-PRIVILEGE SUPER ADMIN ACCOUNT.');
            if (! $this->confirm('Continue?', false)) {
                $this->comment('Aborted.');

                return self::FAILURE;
            }
        } else {
            $this->comment('ENVIRONMENT: '.$this->laravel->environment());
        }

        $name = (string) ($this->option('name') ?: $this->ask('Name'));
        $email = (string) ($this->option('email') ?: $this->ask('Email'));

        // Hidden input — never accept password as a CLI option (shell history / process list).
        $password = (string) $this->secret('Password');
        $confirm = (string) $this->secret('Confirm password');

        try {
            $user = $bootstrap->create($name, $email, $password, $confirm);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('Bootstrap failed. No partial Super Admin was committed.');
            report($e);

            return self::FAILURE;
        } finally {
            // Best-effort wipe of local plaintext references in this process.
            unset($password, $confirm);
        }

        $this->newLine();
        $this->info('Super Admin created successfully.');
        $this->table(['Field', 'Value'], [
            ['User ID', (string) $user->id],
            ['Name', $user->name],
            ['Email', $user->email],
            ['RBAC roles', implode(', ', $user->roleNames())],
        ]);

        $this->newLine();
        $this->line('Next steps:');
        $this->line('  1. Log in normally at /login (no session was created by this command).');
        $this->line('  2. Complete MFA enrollment at /security/mfa/enroll.');
        $this->line('  3. Verify admin access, then create least-privilege staff roles.');
        $this->line('  4. Do not share this Super Admin account.');

        if (! (bool) config('authorization.mfa.enforce_enrollment', false)) {
            $this->newLine();
            $this->warn('WARNING: MFA_ENFORCE_ENROLLMENT is disabled. Super Admin can access admin shell without MFA.');
            $this->warn('For production, set MFA_ENFORCE_ENROLLMENT=true and complete MFA enrollment immediately.');
        } else {
            $this->newLine();
            $this->comment('MFA enrollment is enforced for privileged roles. Complete enrollment before admin use.');
        }

        $this->newLine();
        $this->comment('Bootstrap is now locked. Further Super Admins must be assigned by an existing Super Admin.');

        // Never print password, hash, MFA secret, or recovery codes.
        return self::SUCCESS;
    }
}
