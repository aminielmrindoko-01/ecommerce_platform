<?php

namespace App\Services\Authorization;

use App\Models\AuditLog;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Append-only audit / security event writer.
 * Never logs secrets, tokens, or passwords.
 */
class AuditLogger
{
    public function log(
        string $action,
        ?User $actor = null,
        ?string $resourceType = null,
        mixed $resourceId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        string $result = 'success',
        ?string $reason = null,
        string $category = 'business',
    ): void {
        if (! $this->ready('audit_logs')) {
            return;
        }

        try {
            AuditLog::query()->create([
                'actor_user_id' => $actor?->id,
                'action' => $action,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId !== null ? (string) $resourceId : null,
                'old_values' => $this->scrub($oldValues),
                'new_values' => $this->scrub($newValues),
                'ip_address' => request()?->ip(),
                'user_agent' => Str::limit((string) request()?->userAgent(), 500, ''),
                'request_id' => $this->requestId(),
                'result' => $result,
                'reason' => $reason,
                'category' => $category,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Audit must not break the primary request.
            logger()->warning('audit.log_failed', ['action' => $action]);
        }
    }

    public function security(
        string $event,
        ?User $actor = null,
        string $severity = 'medium',
        ?array $context = null,
    ): void {
        if (! $this->ready('security_events')) {
            return;
        }

        try {
            SecurityEvent::query()->create([
                'actor_user_id' => $actor?->id,
                'event' => $event,
                'severity' => $severity,
                'ip_address' => request()?->ip(),
                'user_agent' => Str::limit((string) request()?->userAgent(), 500, ''),
                'request_id' => $this->requestId(),
                'context' => $this->scrub($context),
                'created_at' => now(),
            ]);

            $this->log(
                action: $event,
                actor: $actor,
                result: 'recorded',
                category: 'security',
                newValues: $context,
            );
        } catch (Throwable) {
            logger()->warning('security_event.log_failed', ['event' => $event]);
        }
    }

    public function permissionDenied(?User $actor, string $permission, ?string $resourceType = null, mixed $resourceId = null): void
    {
        $this->security('PERMISSION_DENIED', $actor, 'medium', [
            'permission' => $permission,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId !== null ? (string) $resourceId : null,
            'path' => request()?->path(),
        ]);
    }

    protected function requestId(): string
    {
        $existing = request()?->headers->get('X-Request-Id');
        if (is_string($existing) && $existing !== '') {
            return Str::limit($existing, 64, '');
        }

        return (string) Str::uuid();
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    protected function scrub(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $blocked = [
            'password', 'password_confirmation', 'token', 'access_token',
            'consumer_secret', 'consumer_key', 'authorization', 'remember_token',
        ];

        $clean = [];
        foreach ($values as $key => $value) {
            if (in_array(strtolower((string) $key), $blocked, true)) {
                $clean[$key] = '[redacted]';

                continue;
            }
            $clean[$key] = $value;
        }

        return $clean;
    }

    protected function ready(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }
}
