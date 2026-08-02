<?php

namespace App\Support\Payments;

/**
 * Safe, non-secret payload returned from gateway initialization.
 *
 * Never indicates a verified charge unless a future live gateway proves it
 * and PaymentService records a paid transition.
 */
final class GatewayInitializationResult
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_COMING_SOON = 'coming_soon';

    public const STATUS_UNAVAILABLE = 'unavailable';

    public const STATUS_PENDING = 'pending';

    public const STATUS_REQUIRES_ACTION = 'requires_action';

    public const STATUS_FAILED = 'failed';

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

    public static function comingSoon(
        string $provider,
        string $methodKey,
        string $methodLabel,
        string $message,
        array $metadata = [],
    ): self {
        return new self(
            self::STATUS_COMING_SOON,
            $provider,
            $methodKey,
            $methodLabel,
            'Payment Service Coming Soon',
            $message,
            $metadata,
        );
    }

    public static function unavailable(
        string $provider,
        string $methodKey,
        string $methodLabel,
        string $message,
        array $metadata = [],
    ): self {
        return new self(
            self::STATUS_UNAVAILABLE,
            $provider,
            $methodKey,
            $methodLabel,
            'Payment Currently Unavailable',
            $message,
            $metadata,
        );
    }

    public static function failed(
        string $provider,
        string $methodKey,
        string $methodLabel,
        string $message,
        array $metadata = [],
    ): self {
        return new self(
            self::STATUS_FAILED,
            $provider,
            $methodKey,
            $methodLabel,
            'Payment Initialization Failed',
            $message,
            $metadata,
        );
    }

    public function isComingSoon(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMING_SOON,
            self::STATUS_UNAVAILABLE,
            self::STATUS_FAILED,
        ], true);
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE
            || $this->status === self::STATUS_REQUIRES_ACTION;
    }

    public function claimsPaymentSuccess(): bool
    {
        // Initialization never marks a payment as paid.
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
