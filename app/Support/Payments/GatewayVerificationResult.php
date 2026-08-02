<?php

namespace App\Support\Payments;

/**
 * Normalized result from a payment gateway verification step.
 */
final class GatewayVerificationResult
{
    /**
     * @param  array<string, mixed>  $metadata  Non-secret metadata only
     */
    public function __construct(
        public readonly bool $successful,
        public readonly ?string $providerReference,
        public readonly string $amount,
        public readonly string $currency,
        public readonly array $metadata = [],
        public readonly ?string $failureReason = null,
    ) {}

    public static function failed(string $reason): self
    {
        return new self(false, null, '0.00', 'TZS', [], $reason);
    }
}
