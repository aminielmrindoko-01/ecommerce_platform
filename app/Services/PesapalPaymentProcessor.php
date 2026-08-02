<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentNotificationReceipt;
use App\Models\PaymentTransaction;
use App\Support\Payments\PesapalGateway;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Coordinates PesaPal verification with PaymentService (sole payment authority).
 *
 * Phase 8B: strict tracking-id + merchant-reference binding before any state mutation.
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
        $merchantReference = trim((string) ($payload['OrderMerchantReference'] ?? $payload['orderMerchantReference'] ?? ''));
        $trackingId = trim((string) ($payload['OrderTrackingId'] ?? $payload['orderTrackingId'] ?? ''));
        $notificationType = trim((string) ($payload['OrderNotificationType'] ?? $payload['orderNotificationType'] ?? 'IPNCHANGE'));

        if ($merchantReference === '' || $trackingId === '') {
            logger()->warning('pesapal.callback.invalid', ['reason' => 'missing_identifiers']);

            return $this->reject(null, null, 'Invalid payment notification.');
        }

        [$receipt, $shouldProcess] = $this->claimReceipt($notificationType, $merchantReference, $trackingId);

        if (! $shouldProcess) {
            $existing = PaymentTransaction::query()->where('reference', $merchantReference)->first();

            return [
                'ok' => true,
                'order' => $existing?->order,
                'payment_status' => $existing?->order?->payment_status,
                'message' => 'Notification already processed.',
            ];
        }

        /** @var PaymentTransaction|null $transaction */
        $transaction = PaymentTransaction::query()
            ->where('reference', $merchantReference)
            ->first();

        if (! $transaction) {
            $this->markReceipt($receipt, PaymentNotificationReceipt::STATUS_REJECTED, 'Unknown payment reference.');

            logger()->warning('pesapal.callback.unknown_reference', [
                'merchant_reference' => $merchantReference,
            ]);

            return $this->reject(null, null, 'Unknown payment reference.');
        }

        $order = $transaction->order;
        if (! $order) {
            $this->markReceipt($receipt, PaymentNotificationReceipt::STATUS_REJECTED, 'Unknown order.');

            return $this->reject(null, null, 'Unknown order.');
        }

        $localTrackingId = $transaction->metadata['pesapal']['order_tracking_id'] ?? null;
        if (! is_string($localTrackingId) || $localTrackingId === '') {
            $this->markReceipt($receipt, PaymentNotificationReceipt::STATUS_REJECTED, 'Local tracking ID missing.');
            logger()->warning('pesapal.callback.missing_local_tracking', [
                'order_id' => $order->id,
                'reference' => $transaction->reference,
            ]);

            return $this->reject($order, $order->payment_status, 'Payment session is not bound to PesaPal yet.');
        }

        if (! hash_equals($localTrackingId, $trackingId)) {
            $this->markReceipt($receipt, PaymentNotificationReceipt::STATUS_REJECTED, 'Tracking ID mismatch.');
            logger()->warning('pesapal.callback.tracking_mismatch', [
                'order_id' => $order->id,
                'reference' => $transaction->reference,
            ]);

            // C1/C3: never mutate on unbound/foreign tracking IDs.
            return $this->reject($order, $order->payment_status, 'Payment notification could not be verified.');
        }

        $verification = $this->gateway->verifyPayment($transaction, [
            'OrderTrackingId' => $trackingId,
            'OrderMerchantReference' => $merchantReference,
        ]);

        if (! $verification->successful) {
            // Binding/config hard-rejects use GatewayVerificationResult::failed() (no status_code).
            // Never mutate payment state for those — leave pending unchanged.
            if (! array_key_exists('status_code', $verification->metadata)) {
                $this->markReceipt(
                    $receipt,
                    PaymentNotificationReceipt::STATUS_REJECTED,
                    $verification->failureReason
                );

                logger()->warning('pesapal.callback.verify_rejected', [
                    'order_id' => $order->id,
                    'reference' => $transaction->reference,
                    'reason' => $verification->failureReason,
                ]);

                return $this->reject($order, $order->payment_status, 'Payment notification could not be verified.');
            }

            $code = (int) $verification->metadata['status_code'];

            // Failed mutations only after full binding + server-to-server verify.
            if ($code === 2) {
                try {
                    $freshTx = $transaction->fresh();
                    if ($freshTx && ! in_array($freshTx->status, ['failed', 'cancelled', 'paid', 'refunded', 'partially_refunded'], true)) {
                        if (($freshTx->status ?: 'pending') === 'pending') {
                            $this->payments->transitionOrderPayment(
                                $order,
                                'processing',
                                null,
                                null,
                                'pesapal',
                                $localTrackingId
                            );
                        }
                        $this->payments->transitionOrderPayment(
                            $order->fresh(),
                            'failed',
                            null,
                            $verification->failureReason ?: 'PesaPal payment failed',
                            'pesapal',
                            $localTrackingId
                        );
                    }
                } catch (InvalidArgumentException|Throwable) {
                    // Illegal transition / already terminal — leave as-is.
                }
            }

            $order = $order->fresh();
            $this->markReceipt(
                $receipt,
                PaymentNotificationReceipt::STATUS_PROCESSED,
                $verification->failureReason
            );

            logger()->info('pesapal.callback.not_completed', [
                'order_id' => $order->id,
                'reference' => $transaction->reference,
                'provider_tracking' => $localTrackingId,
            ]);

            return [
                'ok' => true,
                'order' => $order,
                'payment_status' => $order->payment_status,
                'message' => 'Payment is not completed yet.',
            ];
        }

        $expectedAmount = $this->payments->authoritativeAmount($order);
        $expectedCurrency = strtoupper((string) config('payments.currency', 'TZS'));

        if (bccomp($verification->amount, $expectedAmount, 2) !== 0) {
            $this->markReceipt($receipt, PaymentNotificationReceipt::STATUS_REJECTED, 'Amount mismatch.');
            logger()->warning('pesapal.verify.amount_mismatch', [
                'order_id' => $order->id,
                'reference' => $transaction->reference,
            ]);

            return $this->reject($order, $order->payment_status, 'Payment amount could not be verified.');
        }

        if (strtoupper($verification->currency) !== $expectedCurrency) {
            $this->markReceipt($receipt, PaymentNotificationReceipt::STATUS_REJECTED, 'Currency mismatch.');
            logger()->warning('pesapal.verify.currency_mismatch', [
                'order_id' => $order->id,
                'reference' => $transaction->reference,
            ]);

            return $this->reject($order, $order->payment_status, 'Payment currency could not be verified.');
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
        } catch (InvalidArgumentException) {
            logger()->info('pesapal.verify.transition_noop', [
                'order_id' => $order->id,
                'reference' => $transaction->reference,
                'reason' => 'state_machine_or_conflict',
            ]);

            $order = $order->fresh();

            if ($order->payment_status !== 'paid') {
                $this->markReceipt($receipt, PaymentNotificationReceipt::STATUS_REJECTED, 'Transition rejected.');

                return $this->reject($order, $order->payment_status, 'Payment could not be applied.');
            }
        }

        $order = $order->fresh();
        $this->markReceipt($receipt, PaymentNotificationReceipt::STATUS_PROCESSED, null);

        logger()->info('pesapal.payment.verified', [
            'order_id' => $order->id,
            'reference' => $transaction->reference,
            'provider_tracking' => $verification->providerReference,
        ]);

        return [
            'ok' => true,
            'order' => $order,
            'payment_status' => $order->payment_status,
            'message' => $order->payment_status === 'paid'
                ? 'Payment verified successfully.'
                : 'Payment notification processed.',
        ];
    }

    /**
     * @return array{0: PaymentNotificationReceipt, 1: bool}
     */
    protected function claimReceipt(
        string $notificationType,
        string $merchantReference,
        string $trackingId,
    ): array {
        $key = PaymentNotificationReceipt::makeKey(
            'pesapal',
            $notificationType,
            $merchantReference,
            $trackingId
        );

        return DB::transaction(function () use ($key, $notificationType, $merchantReference, $trackingId) {
            $existing = PaymentNotificationReceipt::query()
                ->where('notification_key', $key)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                // Replay/audit layer: do not re-process the same notification key.
                return [$existing, false];
            }

            $receipt = PaymentNotificationReceipt::query()->create([
                'provider' => 'pesapal',
                'notification_key' => $key,
                'merchant_reference' => $merchantReference,
                'tracking_id' => $trackingId,
                'notification_type' => $notificationType,
                'received_at' => now(),
                'processing_status' => PaymentNotificationReceipt::STATUS_RECEIVED,
            ]);

            return [$receipt, true];
        });
    }

    protected function markReceipt(
        PaymentNotificationReceipt $receipt,
        string $status,
        ?string $failureReason,
    ): void {
        $receipt->forceFill([
            'processing_status' => $status,
            'processed_at' => now(),
            'failure_reason' => $failureReason,
        ])->save();
    }

    /**
     * @return array{ok: bool, order: ?Order, payment_status: ?string, message: string}
     */
    protected function reject(?Order $order, ?string $paymentStatus, string $message): array
    {
        return [
            'ok' => false,
            'order' => $order,
            'payment_status' => $paymentStatus,
            'message' => $message,
        ];
    }
}
