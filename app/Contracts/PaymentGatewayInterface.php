<?php

namespace App\Contracts;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Support\Payments\GatewayInitializationResult;
use App\Support\Payments\GatewayVerificationResult;

/**
 * Future real-gateway adapter contract.
 *
 * Implementations must never trust browser-submitted success; verify provider
 * signatures/amounts server-side before calling PaymentService.
 */
interface PaymentGatewayInterface
{
    /**
     * Provider key (e.g. stub, mpesa, stripe) — not a live integration by itself.
     */
    public function key(): string;

    /**
     * Whether this driver is permitted to perform live charges.
     * Phase 7A stub/coming-soon drivers always return false.
     */
    public function supportsLiveCharging(): bool;

    /**
     * Prepare a provider-side payment intent/session for an order transaction.
     * Must not mark the order paid and must not call external payment APIs in Phase 7A.
     */
    public function initializePayment(Order $order, PaymentTransaction $transaction): GatewayInitializationResult;

    /**
     * Verify a provider callback/webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyPayment(PaymentTransaction $transaction, array $payload): GatewayVerificationResult;
}
