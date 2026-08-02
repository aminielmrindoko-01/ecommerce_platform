<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Support\Payments\PesapalGateway;
use InvalidArgumentException;
use Throwable;

/**
 * Coordinates PesaPal verification with PaymentService (sole payment authority).
 */
class PesapalPaymentProcessor
{
    public function __construct(
        protected PesapalGateway $gateway,
        protected PaymentService $payments,
    ) {}

    /**
     * Independently verify a PesaPal notification and apply PaymentService transitions.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, order: ?Order, payment_status: ?string, message: string}
     */
    public function processNotification(array $payload): array
    {
        $merchantReference = (string) ($payload['OrderMerchantReference'] ?? $payload['orderMerchantReference'] ?? '');
        $trackingId = (string) ($payload['OrderTrackingId'] ?? $payload['orderTrackingId'] ?? '');

        if ($merchantReference === '' || $trackingId === '') {
            logger()->warning('pesapal.callback.invalid', ['reason' => 'missing_identifiers']);

            return [
                'ok' => false,
                'order' => null,
                'payment_status' => null,
                'message' => 'Invalid payment notification.',
            ];
        }

        /** @var PaymentTransaction|null $transaction */
        $transaction = PaymentTransaction::query()
            ->where('reference', $merchantReference)
            ->first();

        if (! $transaction) {
            logger()->warning('pesapal.callback.unknown_reference', [
                'merchant_reference' => $merchantReference,
            ]);

            return [
                'ok' => false,
                'order' => null,
                'payment_status' => null,
                'message' => 'Unknown payment reference.',
            ];
        }

        $order = $transaction->order;
        if (! $order) {
            return [
                'ok' => false,
                'order' => null,
                'payment_status' => null,
                'message' => 'Unknown order.',
            ];
        }

        $verification = $this->gateway->verifyPayment($transaction, [
            'OrderTrackingId' => $trackingId,
            'OrderMerchantReference' => $merchantReference,
        ]);

        if (! $verification->successful) {
            $desc = strtoupper((string) ($verification->metadata['payment_status_description'] ?? ''));
            $code = (int) ($verification->metadata['status_code'] ?? 0);

            if ($code === 2 || $desc === 'FAILED') {
                try {
                    if (! in_array($transaction->status, ['failed', 'cancelled', 'paid', 'refunded', 'partially_refunded'], true)) {
                        if ($transaction->status === 'pending') {
                            $this->payments->transitionOrderPayment($order, 'processing', null, null, 'pesapal', $trackingId);
                        }
                        $this->payments->transitionOrderPayment(
                            $order->fresh(),
                            'failed',
                            null,
                            $verification->failureReason ?: 'PesaPal payment failed',
                            'pesapal',
                            $trackingId
                        );
                    }
                } catch (InvalidArgumentException|Throwable) {
                    // Illegal transition / already terminal — leave as-is.
                }
            }

            logger()->info('pesapal.callback.not_completed', [
                'order_id' => $order->id,
                'reference' => $transaction->reference,
                'provider_tracking' => $trackingId,
            ]);

            return [
                'ok' => true,
                'order' => $order->fresh(),
                'payment_status' => $order->fresh()->payment_status,
                'message' => 'Payment is not completed yet.',
            ];
        }

        // Amount + currency must match authoritative order total.
        $expectedAmount = $this->payments->authoritativeAmount($order);
        $expectedCurrency = strtoupper((string) config('payments.currency', 'TZS'));

        if (bccomp($verification->amount, $expectedAmount, 2) !== 0) {
            logger()->warning('pesapal.verify.amount_mismatch', [
                'order_id' => $order->id,
                'reference' => $transaction->reference,
            ]);

            return [
                'ok' => false,
                'order' => $order,
                'payment_status' => $order->payment_status,
                'message' => 'Payment amount could not be verified.',
            ];
        }

        if (strtoupper($verification->currency) !== $expectedCurrency) {
            logger()->warning('pesapal.verify.currency_mismatch', [
                'order_id' => $order->id,
                'reference' => $transaction->reference,
            ]);

            return [
                'ok' => false,
                'order' => $order,
                'payment_status' => $order->payment_status,
                'message' => 'Payment currency could not be verified.',
            ];
        }

        try {
            $current = $transaction->fresh()->status ?: 'pending';
            if ($current === 'pending') {
                $this->payments->transitionOrderPayment(
                    $order,
                    'processing',
                    null,
                    null,
                    'pesapal',
                    $verification->providerReference
                );
                $order = $order->fresh();
            }

            $this->payments->transitionOrderPayment(
                $order,
                'paid',
                null,
                null,
                'pesapal',
                $verification->providerReference
            );
        } catch (InvalidArgumentException $e) {
            // Idempotent already-paid, illegal transition, or provider-ref conflict.
            logger()->info('pesapal.verify.transition_noop', [
                'order_id' => $order->id,
                'reference' => $transaction->reference,
                'reason' => 'state_machine_or_conflict',
            ]);

            $order = $order->fresh();

            if ($order->payment_status !== 'paid') {
                return [
                    'ok' => false,
                    'order' => $order,
                    'payment_status' => $order->payment_status,
                    'message' => 'Payment could not be applied.',
                ];
            }
        }

        logger()->info('pesapal.payment.verified', [
            'order_id' => $order->id,
            'reference' => $transaction->reference,
            'provider_tracking' => $verification->providerReference,
        ]);

        $order = $order->fresh();

        return [
            'ok' => true,
            'order' => $order,
            'payment_status' => $order->payment_status,
            'message' => $order->payment_status === 'paid'
                ? 'Payment verified successfully.'
                : 'Payment notification processed.',
        ];
    }
}
