<?php

namespace App\Contracts;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Support\Payments\GatewayVerificationResult;

/**
 * Future real-gateway adapter contract.
 *
 * Phase 6 readiness only — no live charging. Implementations must never
 * trust browser-submitted success; verify provider signatures/amounts
 * server-side before calling PaymentService.
 */
interface PaymentGatewayInterface
{
    /**
     * Provider key (e.g. stub, mpesa, stripe) — not a live integration by itself.
     */
    public function key(): string;

    /**
     * Prepare a provider-side payment intent/session for an order transaction.
     *
     * @return array<string, mixed> Non-secret initialization payload for the client/UI
     */
    public function initializePayment(Order $order, PaymentTransaction $transaction): array;

    /**
     * Verify a provider callback/webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyPayment(PaymentTransaction $transaction, array $payload): GatewayVerificationResult;
}
