<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Provider notification receipt for replay protection / audit (not payment authority).
 */
class PaymentNotificationReceipt extends Model
{
    public const STATUS_RECEIVED = 'received';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'provider',
        'notification_key',
        'merchant_reference',
        'tracking_id',
        'notification_type',
        'received_at',
        'processed_at',
        'processing_status',
        'failure_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public static function makeKey(
        string $provider,
        string $notificationType,
        string $merchantReference,
        string $trackingId,
    ): string {
        return hash('sha256', implode('|', [
            strtolower($provider),
            strtoupper($notificationType),
            $merchantReference,
            $trackingId,
        ]));
    }
}
