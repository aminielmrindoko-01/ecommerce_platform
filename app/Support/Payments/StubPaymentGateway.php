<?php

namespace App\Support\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use App\Models\PaymentTransaction;

/**
 * Non-charging stub / coming-soon gateway.
 *
 * Does not contact external networks and must never mark a payment as paid.
 */
class StubPaymentGateway implements PaymentGatewayInterface
{
    public function key(): string
    {
        return 'stub';
    }

    public function supportsLiveCharging(): bool
    {
        return false;
    }

    public function initializePayment(Order $order, PaymentTransaction $transaction): GatewayInitializationResult
    {
        $methodKey = (string) ($order->payment_method ?: 'unknown');
        $methodLabel = (string) config("payments.methods.{$methodKey}.label", $this->humanize($methodKey));
        $offline = (bool) config("payments.methods.{$methodKey}.offline", false);

        if ($offline) {
            return new GatewayInitializationResult(
                status: GatewayInitializationResult::STATUS_PENDING,
                provider: $this->key(),
                methodKey: $methodKey,
                methodLabel: $methodLabel,
                headline: $methodLabel,
                message: $methodKey === 'cod'
                    ? 'Pay when your order arrives. Payment status stays pending until our team confirms receipt.'
                    : 'Complete this offline payment using the details shared by our team. Online confirmation remains pending until verified.',
                metadata: [
                    'reference' => $transaction->reference,
                    'amount' => (string) $transaction->amount,
                    'currency' => $transaction->currency,
                    'mode' => 'offline',
                    'live_charging' => false,
                ],
            );
        }

        return new GatewayInitializationResult(
            status: GatewayInitializationResult::STATUS_COMING_SOON,
            provider: $this->key(),
            methodKey: $methodKey,
            methodLabel: $methodLabel,
            headline: 'Payment Service Coming Soon',
            message: "We're preparing secure online payments for {$methodLabel}. This payment method is not currently available, but we're working to make it available soon.",
            metadata: [
                'reference' => $transaction->reference,
                'amount' => (string) $transaction->amount,
                'currency' => $transaction->currency,
                'mode' => 'coming_soon',
                'live_charging' => false,
            ],
        );
    }

    public function verifyPayment(PaymentTransaction $transaction, array $payload): GatewayVerificationResult
    {
        return GatewayVerificationResult::failed(
            'Stub gateway does not verify live payments. Use admin/manual PaymentService transitions.'
        );
    }

    protected function humanize(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }
}
