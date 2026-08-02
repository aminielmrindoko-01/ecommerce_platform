<?php

namespace App\Support\Payments;

/**
 * Safe, non-secret payload returned from gateway initialization.
 *
 * Never indicates a verified charge unless a future live gateway proves it.
 */
final class GatewayInitializationResult
{
    public const STATUS_COMING_SOON = 'coming_soon';

    public const STATUS_UNAVAILABLE = 'unavailable';

    public const STATUS_PENDING = 'pending';

    public const STATUS_REQUIRES_ACTION = 'requires_action';

    /**
     * @param  array<string, mixed>  $metadata  Non-secret metadata only
     */
    public function __construct(
        public readonly string $status,
        public readonly string $provider,
        public readonly string $methodKey,
        public readonly string $methodLabel,
        public readonly string $headline,
        public readonly string $message,
        public readonly array $metadata = [],
    ) {}

    public function isComingSoon(): bool
    {
        return $this->status === self::STATUS_COMING_SOON
            || $this->status === self::STATUS_UNAVAILABLE;
    }

    public function claimsPaymentSuccess(): bool
    {
        // Initialization never marks a payment as paid in Phase 7A.
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'provider' => $this->provider,
            'method_key' => $this->methodKey,
            'method_label' => $this->methodLabel,
            'headline' => $this->headline,
            'message' => $this->message,
            'metadata' => $this->metadata,
        ];
    }
}
