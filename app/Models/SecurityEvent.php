<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * High-signal security events (failed auth, privilege attempts, etc.).
 * Append-only — ordinary admins must not mutate or delete rows.
 */
class SecurityEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_user_id',
        'event',
        'severity',
        'ip_address',
        'user_agent',
        'request_id',
        'context',
        'created_at',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Security events are immutable.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Security events are immutable.');
        });
    }

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
