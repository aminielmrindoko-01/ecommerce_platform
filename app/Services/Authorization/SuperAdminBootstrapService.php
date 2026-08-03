<?php

namespace App\Services\Authorization;

use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Secure first Super Admin bootstrap (CLI / infrastructure only).
 *
 * Locked once any RBAC super_admin exists. Never creates HTTP endpoints or backdoors.
 */
class SuperAdminBootstrapService
{
    public function __construct(
        protected RoleAssignmentService $roles,
        protected PermissionResolver $permissions,
        protected AuditLogger $audit,
    ) {}

    /**
     * Create the first Super Admin atomically.
     *
     * @throws ValidationException
     * @throws InvalidArgumentException
     */
    public function create(string $name, string $email, string $password, string $passwordConfirmation): User
    {
        $this->validateInput($name, $email, $password, $passwordConfirmation);
        $this->ensureRbacCatalog();

        if ($this->roles->superAdminExists()) {
            throw new InvalidArgumentException(
                'A Super Admin already exists. The bootstrap command is only for initial setup and is now locked.'
            );
        }

        return DB::transaction(function () use ($name, $email, $password) {
            // Re-check under lock inside RoleAssignmentService::bootstrapFirstSuperAdmin.
            if (User::query()->where('email', $email)->lockForUpdate()->exists()) {
                throw new InvalidArgumentException('A user with this email already exists. Bootstrap will not modify existing accounts.');
            }

            $user = new User;
            $user->fill([
                'name' => $name,
                'email' => $email,
                'password' => $password, // hashed via model cast — never store plaintext
            ]);
            // Marketplace identity placeholder; RBAC assignment is authoritative for privileges.
            $user->forceFill([
                'role' => 'customer',
                'is_active' => true,
                'email_verified_at' => now(),
            ])->save();

            $this->roles->bootstrapFirstSuperAdmin($user->fresh());

            $user = $user->fresh(['roles']);
            $this->permissions->forget($user);

            $this->audit->security('SUPER_ADMIN_BOOTSTRAPPED', null, 'critical', [
                'actor' => 'system:cli',
                'created_user_id' => $user->id,
                'email' => $user->email,
                'roles' => $user->roleNames(),
                'mfa_enforced' => (bool) config('authorization.mfa.enforce_enrollment', false),
                'result' => 'success',
            ]);

            return $user;
        });
    }

    public function isLocked(): bool
    {
        $this->ensureRbacCatalog();

        return $this->roles->superAdminExists();
    }

    protected function validateInput(string $name, string $email, string $password, string $passwordConfirmation): void
    {
        $validator = Validator::make(
            [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
            ],
            [
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                // Match application registration policy.
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ],
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Ensure role/permission catalog exists (idempotent). Does not create users.
     */
    protected function ensureRbacCatalog(): void
    {
        if (! \App\Models\Role::query()->where('name', 'super_admin')->exists()) {
            (new RbacSeeder)->run();
        }
    }

    /**
     * Sanity helper for tests/operators — never used to authenticate.
     */
    public function passwordIsHashed(User $user, string $plain): bool
    {
        $hash = $user->getAttributes()['password'] ?? '';

        return is_string($hash)
            && $hash !== $plain
            && Hash::check($plain, $hash);
    }
}
