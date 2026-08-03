<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * High-signal security events (failed auth, privilege attempts, etc.).
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
