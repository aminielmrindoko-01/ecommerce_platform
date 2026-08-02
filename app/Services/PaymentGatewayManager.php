<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Support\Payments\GatewayInitializationResult;
use App\Support\Payments\PaymentStatusPresenter;
use App\Support\Payments\PesapalGateway;
use App\Support\Payments\StubPaymentGateway;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Throwable;

/**
 * Resolves configured payment gateways for checkout methods.
 *
 * Fail closed: missing/misconfigured live drivers fall back to the non-charging
 * stub. Never marks an order paid and never performs live charges in Phase 7B.
 *
 * Future extension points (not registered yet):
 * mpesa, airtel, tigo, stripe, paypal drivers implementing PaymentGatewayInterface.
 */
class PaymentGatewayManager
{
    public function __construct(
        protected Container $container,
    ) {}

    /**
     * @return array<string, array{key: string, label: string, gateway: string, offline: bool, group: string, live_charging: bool, coming_soon: bool, available: bool, badge: string, gateway_display: string}>
     */
    public function checkoutMethods(): array
    {
        $methods = [];

        foreach (array_keys(config('payments.methods', [])) as $key) {
            $methods[$key] = $this->describeMethod($key);
        }

        return $methods;
    }

    /**
     * @return list<string>
     */
    public function methodKeys(): array
    {
        return array_keys(config('payments.methods', []));
    }

    /**
     * @return array{key: string, label: string, gateway: string, offline: bool, group: string, live_charging: bool, coming_soon: bool, available: bool, badge: string, gateway_display: string}
     */
    public function describeMethod(string $method): array
    {
        if (! $this->hasMethod($method)) {
            throw new InvalidArgumentException('Unknown payment method.');
        }

        $label = (string) config("payments.methods.{$method}.label", $method);
        $gateway = (string) config("payments.methods.{$method}.gateway", 'stub');
        $offline = (bool) config("payments.methods.{$method}.offline", false);
        $group = (string) config("payments.methods.{$method}.group", $offline ? 'offline' : 'online');
        $live = $this->isLiveChargingConfigured($method);

        return [
            'key' => $method,
            'label' => $label,
            'gateway' => $gateway,
            'offline' => $offline,
            'group' => $group,
            'live_charging' => $live,
            'coming_soon' => ! $live && ! $offline,
            'available' => $live,
            'badge' => $live ? 'Available' : ($offline ? 'Offline' : 'Coming soon'),
            'gateway_display' => PaymentStatusPresenter::gatewayDisplayName($gateway),
        ];
    }

    public function hasMethod(string $method): bool
    {
        return array_key_exists($method, config('payments.methods', []));
    }

    public function isLiveChargingConfigured(string $method): bool
    {
        if (! $this->hasMethod($method)) {
            return false;
        }

        $gatewayKey = (string) config("payments.methods.{$method}.gateway", 'stub');

        return $this->gatewayAllowsLiveCharging($gatewayKey);
    }

    public function gatewayAllowsLiveCharging(string $gatewayKey): bool
    {
        $gateway = config("payments.gateways.{$gatewayKey}");

        if (! is_array($gateway)) {
            return false;
        }

        if (! (bool) ($gateway['enabled'] ?? false) || ! (bool) ($gateway['live_charging'] ?? false)) {
            return false;
        }

        // Phase 8A: PesaPal may only charge in sandbox with credentials present.
        if ($gatewayKey === 'pesapal') {
            if (strtolower((string) ($gateway['environment'] ?? '')) !== 'sandbox') {
                return false;
            }

            if (! filled($gateway['consumer_key'] ?? null) || ! filled($gateway['consumer_secret'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    public function activeGatewayKey(): string
    {
        return (string) config('payments.default', 'stub');
    }

    public function activeGatewayDisplayName(): string
    {
        return PaymentStatusPresenter::gatewayDisplayName($this->activeGatewayKey());
    }

    public function default(): PaymentGatewayInterface
    {
        return $this->resolveOrStub($this->activeGatewayKey());
    }

    /**
     * Strict resolve — unknown keys throw. Use resolveOrStub() for checkout safety.
     */
    public function resolve(string $gatewayKey): PaymentGatewayInterface
    {
        $config = config("payments.gateways.{$gatewayKey}");

        if (! is_array($config)) {
            throw new InvalidArgumentException('Unknown payment gateway.');
        }

        if (! $this->gatewayAllowsLiveCharging($gatewayKey)) {
            return $this->stub();
        }

        $driver = (string) ($config['driver'] ?? $gatewayKey);

        return match ($driver) {
            'stub' => $this->stub(),
            'pesapal' => $this->container->make(PesapalGateway::class),
            // Future: 'mpesa' => $this->container->make(MpesaGateway::class),
            // Future: 'airtel' => $this->container->make(AirtelMoneyGateway::class),
            // Future: 'tigo' => $this->container->make(TigoPesaGateway::class),
            // Future: 'stripe' => $this->container->make(StripeGateway::class),
            // Future: 'paypal' => $this->container->make(PayPalGateway::class),
            default => throw new InvalidArgumentException('Payment gateway driver is not available.'),
        };
    }

    /**
     * Fail-closed resolve for customer checkout paths.
     */
    public function resolveOrStub(string $gatewayKey): PaymentGatewayInterface
    {
        try {
            return $this->resolve($gatewayKey);
        } catch (Throwable $e) {
            logger()->warning('payment.gateway.resolve_fallback_stub', [
                'gateway' => $gatewayKey,
                'reason' => 'unavailable_or_misconfigured',
            ]);

            return $this->stub();
        }
    }

    public function resolveForMethod(string $method): PaymentGatewayInterface
    {
        if (! $this->hasMethod($method)) {
            throw new InvalidArgumentException('Unknown payment method.');
        }

        $gatewayKey = (string) config("payments.methods.{$method}.gateway", 'stub');

        return $this->resolve($gatewayKey);
    }

    /**
     * Initialize payment UI state for an order. Never marks payment as paid.
     * Fail closed on unexpected errors — payment remains pending.
     */
    public function initialize(Order $order, PaymentTransaction $transaction): GatewayInitializationResult
    {
        $method = (string) ($order->payment_method ?: '');
        $methodLabel = $method !== '' && $this->hasMethod($method)
            ? (string) config("payments.methods.{$method}.label", $method)
            : 'Selected method';

        try {
            if ($method === '' || ! $this->hasMethod($method)) {
                $gateway = $this->default();
            } else {
                $gatewayKey = (string) config("payments.methods.{$method}.gateway", 'stub');
                $gateway = $this->resolveOrStub($gatewayKey);
            }

            $result = $gateway->initializePayment($order, $transaction);

            logger()->info('payment.gateway.initialized', [
                'gateway' => $gateway->key(),
                'order_id' => $order->id,
                'reference' => $transaction->reference,
                'status' => $result->status,
                'live_charging' => $gateway->supportsLiveCharging(),
            ]);

            return $result;
        } catch (Throwable) {
            logger()->warning('payment.gateway.initialize_failed_closed', [
                'order_id' => $order->id,
                'reference' => $transaction->reference,
                'method' => $method,
            ]);

            return GatewayInitializationResult::unavailable(
                'stub',
                $method !== '' ? $method : 'unknown',
                $methodLabel,
                'Online payment is currently unavailable. Your order is saved and no payment has been charged.',
                [
                    'reference' => $transaction->reference,
                    'amount' => (string) $transaction->amount,
                    'currency' => $transaction->currency,
                    'mode' => 'unavailable',
                    'live_charging' => false,
                ],
            );
        }
    }

    protected function stub(): StubPaymentGateway
    {
        return $this->container->make(StubPaymentGateway::class);
    }
}
