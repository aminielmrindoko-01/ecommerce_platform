<?php

namespace App\Support\Payments;

/**
 * Human-readable payment status labels for Blade (no secrets).
 */
final class PaymentStatusPresenter
{
    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'pending' => 'Pending',
            'processing' => 'Processing',
            'paid' => 'Paid',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
            'partially_refunded' => 'Partially Refunded',
        ];
    }

    public static function label(?string $status): string
    {
        $status = strtolower((string) $status);

        return self::labels()[$status] ?? ($status !== '' ? ucfirst(str_replace('_', ' ', $status)) : 'Pending');
    }

    public static function gatewayDisplayName(?string $gatewayKey = null): string
    {
        $key = $gatewayKey ?: (string) config('payments.default', 'stub');
        $name = config("payments.gateways.{$key}.display_name");

        if (is_string($name) && $name !== '') {
            return $name;
        }

        return 'Stub / Offline / Coming Soon';
    }
}
