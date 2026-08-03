<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Saved shipping address for a buyer (account + checkout save-address).
 *
 * Only one address should be `is_default` per user (enforced in controllers).
 *
 * @package App\Models
 */
class Address extends Model
{
    protected $fillable = [
        'label',
        'full_name',
        'phone',
        'line1',
        'line2',
        'city',
        'region',
        'postal_code',
        'country',
        'is_default',
        // user_id is assigned server-side only
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
