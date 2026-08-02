<?php

namespace App\Support\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use App\Models\PaymentTransaction;

/**
 * Non-charging stub gateway for architecture readiness tests.
 *
 * Does not contact external networks and must not be treated as a live integration.
 */
class StubPaymentGateway implements PaymentGatewayInterface
{
    public function key(): string
    {
        return 'stub';
    }

    public function initializePayment(Order $order, PaymentTransaction $transaction): array
    {
        return [
            'provider' => $this->key(),
            'reference' => $transaction->reference,
            'amount' => (string) $transaction->amount,
            'currency' => $transaction->currency,
            'mode' => 'stub',
            'message' => 'Stub gateway — no live charge is performed.',
        ];
    }

    public function verifyPayment(PaymentTransaction $transaction, array $payload): GatewayVerificationResult
    {
        // Intentionally conservative: stub never auto-approves live charges.
        return GatewayVerificationResult::failed(
            'Stub gateway does not verify live payments. Use admin/manual PaymentService transitions.'
        );
    }
}
