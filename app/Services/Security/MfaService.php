<?php

namespace App\Services\Security;

use App\Models\User;
use App\Services\Authorization\AuditLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * MFA enrollment / verification / recovery for privileged accounts.
 * Secrets are stored encrypted via User casts; never returned after confirm.
 */
class MfaService
{
    public function __construct(
        protected TotpService $totp,
        protected AuditLogger $audit,
    ) {}

    /**
     * Begin enrollment — returns secret + otpauth URI once (do not log/store in session long-term beyond pending).
     *
     * @return array{secret: string, otpauth_uri: string, recovery_codes: list<string>}
     */
    public function beginEnrollment(User $user): array
    {
        $secret = $this->totp->generateSecret();
        $recovery = $this->generateRecoveryCodes();

        $user->forceFill([
            'mfa_secret' => $secret,
            'mfa_enabled' => false,
            'mfa_confirmed_at' => null,
            'mfa_recovery_codes' => array_map(fn ($c) => Hash::make($c), $recovery),
        ])->save();

        $this->audit->log(
            action: 'MFA_ENROLLMENT_STARTED',
            actor: $user,
            resourceType: 'user',
            resourceId: $user->id,
            category: 'security',
        );

        return [
            'secret' => $secret,
            'otpauth_uri' => $this->totp->provisioningUri($secret, (string) $user->email),
            'recovery_codes' => $recovery,
        ];
    }

    public function confirmEnrollment(User $user, string $code): void
    {
        if (! filled($user->mfa_secret)) {
            throw new InvalidArgumentException('MFA enrollment has not been started.');
        }

        if (! $this->totp->verify((string) $user->mfa_secret, $code)) {
            $this->audit->security('MFA_VERIFY_FAILED', $user, 'medium', ['phase' => 'enrollment']);
            throw new InvalidArgumentException('Invalid authenticator code.');
        }

        $user->forceFill([
            'mfa_enabled' => true,
            'mfa_confirmed_at' => now(),
        ])->save();

        $this->audit->log(
            action: 'MFA_ENABLED',
            actor: $user,
            resourceType: 'user',
            resourceId: $user->id,
            category: 'security',
        );
    }

    public function verifyLogin(User $user, string $code): bool
    {
        if (! $user->hasMfaEnabled()) {
            return false;
        }

        if ($this->totp->verify((string) $user->mfa_secret, $code)) {
            $this->audit->log(
                action: 'MFA_CHALLENGE_PASSED',
                actor: $user,
                resourceType: 'user',
                resourceId: $user->id,
                category: 'security',
            );

            return true;
        }

        // Recovery codes (single-use).
        $codes = $user->mfa_recovery_codes ?? [];
        foreach ($codes as $index => $hash) {
            if (is_string($hash) && Hash::check($code, $hash)) {
                unset($codes[$index]);
                $user->forceFill(['mfa_recovery_codes' => array_values($codes)])->save();
                $this->audit->log(
                    action: 'MFA_RECOVERY_USED',
                    actor: $user,
                    resourceType: 'user',
                    resourceId: $user->id,
                    category: 'security',
                );

                return true;
            }
        }

        $this->audit->security('MFA_VERIFY_FAILED', $user, 'medium', ['phase' => 'login']);

        return false;
    }

    public function disable(User $user, string $code, bool $stepUpConfirmed): void
    {
        if (! $stepUpConfirmed) {
            throw new InvalidArgumentException('Step-up authentication required to disable MFA.');
        }

        if (! $user->hasMfaEnabled() || ! $this->totp->verify((string) $user->mfa_secret, $code)) {
            $this->audit->security('MFA_DISABLE_FAILED', $user, 'high', []);
            throw new InvalidArgumentException('Invalid authenticator code.');
        }

        $user->forceFill([
            'mfa_enabled' => false,
            'mfa_secret' => null,
            'mfa_confirmed_at' => null,
            'mfa_recovery_codes' => null,
        ])->save();

        $this->audit->log(
            action: 'MFA_DISABLED',
            actor: $user,
            resourceType: 'user',
            resourceId: $user->id,
            category: 'security',
        );
    }

    /**
     * @return list<string>
     */
    protected function generateRecoveryCodes(): array
    {
        $count = (int) config('authorization.mfa.recovery_codes_count', 8);
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = Str::upper(Str::random(4).'-'.Str::random(4));
        }

        return $codes;
    }
}
