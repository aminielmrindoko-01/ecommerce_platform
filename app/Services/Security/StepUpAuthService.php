<?php

namespace App\Services\Security;

use App\Models\User;
use App\Services\Authorization\AuditLogger;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Recent-authentication gate for highly sensitive actions.
 */
class StepUpAuthService
{
    public const SESSION_KEY = 'auth.step_up_confirmed_at';

    public function __construct(
        protected AuditLogger $audit,
    ) {}

    public function ttlSeconds(): int
    {
        return max(60, (int) config('authorization.step_up.ttl_seconds', 300));
    }

    public function confirm(User $user, string $password): void
    {
        if (! Hash::check($password, (string) $user->password)) {
            $this->audit->security('STEP_UP_FAILED', $user, 'high', [
                'reason' => 'bad_password',
            ]);
            throw new HttpException(403, 'Step-up authentication failed.');
        }

        session([self::SESSION_KEY => now()->timestamp]);

        $this->audit->log(
            action: 'STEP_UP_CONFIRMED',
            actor: $user,
            resourceType: 'user',
            resourceId: $user->id,
            category: 'security',
        );
    }

    public function isSatisfied(): bool
    {
        $confirmedAt = session(self::SESSION_KEY);
        if (! is_numeric($confirmedAt)) {
            return false;
        }

        return (now()->timestamp - (int) $confirmedAt) <= $this->ttlSeconds();
    }

    public function assertSatisfied(?User $user = null): void
    {
        if ($this->isSatisfied()) {
            return;
        }

        if ($user) {
            $this->audit->security('STEP_UP_REQUIRED', $user, 'medium', [
                'path' => request()?->path(),
            ]);
        }

        throw new HttpException(403, 'Recent authentication required for this action.');
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
