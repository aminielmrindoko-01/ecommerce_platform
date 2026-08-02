<?php

namespace App\Support\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\PaymentService;
use RuntimeException;
use Throwable;

/**
 * PesaPal API 3.0 sandbox adapter (Phase 8B hardened).
 *
 * Communicates with PesaPal only. Never marks orders paid — PaymentService owns transitions.
 * Phase 8A/8B permit sandbox environment exclusively.
 */
class PesapalGateway implements PaymentGatewayInterface
{
    public function __construct(
        protected PesapalClient $client,
        protected PaymentService $payments,
    ) {}

    public function key(): string
    {
        return 'pesapal';
    }

    public function supportsLiveCharging(): bool
    {
        $config = (array) config('payments.gateways.pesapal', []);

        return (bool) ($config['enabled'] ?? false)
            && (bool) ($config['live_charging'] ?? false)
            && $this->client->isSandboxOnlyAllowed()
            && $this->client->hasCredentials();
    }

    /**
     * Allow-list check for provider redirect URLs (sandbox hosts only).
     */
    public function isAllowedRedirectUrl(?string $url): bool
    {
        if (! is_string($url) || $url === '') {
            return false;
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['https'], true) || $host === '') {
            return false;
        }

        $allowed = array_map(
            'strtolower',
            (array) config('payments.gateways.pesapal.allowed_redirect_hosts', ['cybqa.pesapal.com'])
        );

        return in_array($host, $allowed, true);
    }

    public function initializePayment(Order $order, PaymentTransaction $transaction): GatewayInitializationResult
    {
        $methodKey = (string) ($order->payment_method ?: 'pesapal');
        $methodLabel = (string) config("payments.methods.{$methodKey}.label", 'PesaPal');

        if (! $this->client->isSandboxOnlyAllowed()) {
            logger()->warning('pesapal.initialize.production_rejected', [
                'order_id' => $order->id,
                'reference' => $transaction->reference,
            ]);

            return GatewayInitializationResult::unavailable(
                $this->key(),
                $methodKey,
                $methodLabel,
                'Online payment is currently unavailable. No payment has been charged.',
            );
        }

        if (! $this->supportsLiveCharging()) {
            return GatewayInitializationResult::comingSoon(
                $this->key(),
                $methodKey,
                $methodLabel,
                'PesaPal sandbox is not configured. Online payment is currently unavailable. You can place your order now — no payment has been charged.',
                [
                    'reference' => $transaction->reference,
                    'amount' => $this->payments->normalizeMoney($transaction->amount),
                    'currency' => $transaction->currency,
                    'mode' => 'coming_soon',
                    'live_charging' => false,
                ],
            );
        }

        if (($order->payment_status ?: 'pending') === 'paid' || $transaction->status === 'paid') {
            return new GatewayInitializationResult(
                status: GatewayInitializationResult::STATUS_PENDING,
                provider: $this->key(),
                methodKey: $methodKey,
                methodLabel: $methodLabel,
                headline: 'Payment already recorded',
                message: 'This order already has a recorded payment.',
                metadata: [
                    'reference' => $transaction->reference,
                    'mode' => 'already_paid',
                ],
            );
        }

        try {
            $amount = $this->payments->authoritativeAmount($order);
            $currency = strtoupper((string) ($transaction->currency ?: config('payments.currency', 'TZS')));

            if (! in_array($currency, config('payments.currencies', ['TZS']), true)) {
                return GatewayInitializationResult::failed(
                    $this->key(),
                    $methodKey,
                    $methodLabel,
                    'Unsupported payment currency.',
                );
            }

            if (bccomp($amount, '0.00', 2) <= 0) {
                return GatewayInitializationResult::failed(
                    $this->key(),
                    $methodKey,
                    $methodLabel,
                    'Invalid payment amount.',
                );
            }

            $callbackUrl = (string) (config('payments.gateways.pesapal.callback_url') ?: route('payments.pesapal.callback'));
            $ipnUrl = (string) (config('payments.gateways.pesapal.ipn_url') ?: route('payments.pesapal.ipn'));
            $notificationId = $this->client->registerIpn($ipnUrl, 'POST');

            $user = $order->user;
            $shipping = is_array($order->shipping_address) ? $order->shipping_address : [];

            // Decimal-safe: send authoritative money string (no binary float cast).
            $payload = [
                'id' => $transaction->reference,
                'currency' => $currency,
                'amount' => $amount,
                'description' => substr('Order '.$order->order_number, 0, 100),
                'callback_url' => $callbackUrl,
                'notification_id' => $notificationId,
                'billing_address' => [
                    'email_address' => $user?->email,
                    'phone_number' => $shipping['phone'] ?? $user?->phone,
                    'country_code' => 'TZ',
                    'first_name' => $shipping['full_name'] ?? $user?->name,
                    'middle_name' => '',
                    'last_name' => '',
                    'line_1' => $shipping['line1'] ?? '',
                    'line_2' => $shipping['line2'] ?? '',
                    'city' => $shipping['city'] ?? '',
                    'state' => substr((string) ($shipping['region'] ?? ''), 0, 3),
                    'postal_code' => '',
                    'zip_code' => '',
                ],
            ];

            $response = $this->client->submitOrderRequest($payload);
            $trackingId = $response['order_tracking_id'] ?? null;
            $redirectUrl = $response['redirect_url'] ?? null;

            if (! is_string($trackingId) || $trackingId === '') {
                logger()->warning('pesapal.initialize.missing_tracking', [
                    'order_id' => $order->id,
                    'reference' => $transaction->reference,
                ]);

                return GatewayInitializationResult::failed(
                    $this->key(),
                    $methodKey,
                    $methodLabel,
                    'Unable to start PesaPal checkout right now. No payment has been charged.',
                );
            }

            if (! $this->isAllowedRedirectUrl(is_string($redirectUrl) ? $redirectUrl : null)) {
                logger()->warning('pesapal.initialize.invalid_redirect_host', [
                    'order_id' => $order->id,
                    'reference' => $transaction->reference,
                ]);

                return GatewayInitializationResult::failed(
                    $this->key(),
                    $methodKey,
                    $methodLabel,
                    'Unable to start PesaPal checkout right now. No payment has been charged.',
                );
            }

            $meta = $transaction->metadata ?? [];
            $meta['pesapal'] = [
                'order_tracking_id' => $trackingId,
                'merchant_reference' => $transaction->reference,
                'environment' => 'sandbox',
            ];
            $transaction->metadata = $meta;
            $transaction->save();

            logger()->info('pesapal.initialize.success', [
                'order_id' => $order->id,
                'reference' => $transaction->reference,
                'provider_tracking' => $trackingId,
            ]);

            return new GatewayInitializationResult(
                status: GatewayInitializationResult::STATUS_REQUIRES_ACTION,
                provider: $this->key(),
                methodKey: $methodKey,
                methodLabel: $methodLabel,
                headline: 'Continue to PesaPal',
                message: 'Your order is saved. Complete payment on PesaPal sandbox. Returning from PesaPal does not mark the order paid until payment is independently verified.',
                metadata: [
                    'reference' => $transaction->reference,
                    'amount' => $amount,
                    'currency' => $currency,
                    'redirect_url' => $redirectUrl,
                    'order_tracking_id' => $trackingId,
                    'mode' => 'pesapal_sandbox',
                    'live_charging' => true,
                ],
            );
        } catch (Throwable) {
            logger()->warning('pesapal.initialize.failed', [
                'order_id' => $order->id,
                'reference' => $transaction->reference,
                'reason' => 'api_or_config_failure',
            ]);

            return GatewayInitializationResult::failed(
                $this->key(),
                $methodKey,
                $methodLabel,
                'Unable to start online payment right now. Your order is saved and no payment has been charged.',
            );
        }
    }

    public function verifyPayment(PaymentTransaction $transaction, array $payload): GatewayVerificationResult
    {
        if (! $this->client->isSandboxOnlyAllowed()) {
            return GatewayVerificationResult::failed('PesaPal production environment is not permitted in Phase 8A/8B.');
        }

        if (! $this->client->hasCredentials()) {
            return GatewayVerificationResult::failed('PesaPal credentials are not configured.');
        }

        $localTrackingId = $transaction->metadata['pesapal']['order_tracking_id'] ?? null;
        if (! is_string($localTrackingId) || $localTrackingId === '') {
            return GatewayVerificationResult::failed('Local PesaPal tracking ID is missing.');
        }

        $incomingTrackingId = $payload['OrderTrackingId'] ?? $payload['orderTrackingId'] ?? null;
        if (! is_string($incomingTrackingId) || $incomingTrackingId === '') {
            return GatewayVerificationResult::failed('Missing PesaPal order tracking id.');
        }

        if (! hash_equals($localTrackingId, $incomingTrackingId)) {
            return GatewayVerificationResult::failed('PesaPal tracking ID does not match this payment.');
        }

        try {
            $status = $this->client->getTransactionStatus($localTrackingId);
        } catch (RuntimeException) {
            logger()->warning('pesapal.verify.failed', [
                'payment_transaction_id' => $transaction->id,
                'order_id' => $transaction->order_id,
                'reason' => 'status_request_failed',
            ]);

            return GatewayVerificationResult::failed('Unable to verify PesaPal payment status.');
        }

        $merchantReference = $status['merchant_reference'] ?? null;
        if (! is_string($merchantReference) || trim($merchantReference) === '') {
            return GatewayVerificationResult::failed('PesaPal merchant reference missing from status response.');
        }

        if (! hash_equals((string) $transaction->reference, $merchantReference)) {
            return GatewayVerificationResult::failed('PesaPal merchant reference mismatch.');
        }

        $amountRaw = $status['amount'] ?? null;
        $currency = strtoupper((string) ($status['currency'] ?? ''));
        $statusCode = (int) ($status['status_code'] ?? 0);

        if ($amountRaw === null || $amountRaw === '' || $currency === '') {
            return GatewayVerificationResult::failed('PesaPal status missing amount or currency.');
        }

        try {
            $amount = $this->payments->normalizeMoney($amountRaw);
        } catch (Throwable) {
            return GatewayVerificationResult::failed('PesaPal status contained a malformed amount.');
        }

        // status_code === 1 is the ONLY authoritative success condition.
        $successful = $statusCode === 1;

        return new GatewayVerificationResult(
            successful: $successful,
            providerReference: $localTrackingId,
            amount: $amount,
            currency: $currency,
            metadata: [
                'status_code' => $statusCode,
                'payment_status_description' => $status['payment_status_description'] ?? null,
                'payment_method' => $status['payment_method'] ?? null,
                'merchant_reference' => $merchantReference,
            ],
            failureReason: $successful ? null : ('PesaPal status_code='.$statusCode),
        );
    }
}
