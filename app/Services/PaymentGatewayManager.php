<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Support\Payments\GatewayInitializationResult;
use App\Support\Payments\StubPaymentGateway;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resolves configured payment gateways for checkout methods.
 *
 * Phase 7A: live charging is disabled for every provider. Selection falls back
 * to the non-charging stub / coming-soon driver.
 */
class PaymentGatewayManager
{
    public function __construct(
        protected Container $container,
    ) {}

    /**
     * @return array<string, array{key: string, label: string, gateway: string, offline: bool, live_charging: bool, coming_soon: bool, badge: string}>
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
     * @return array{key: string, label: string, gateway: string, offline: bool, live_charging: bool, coming_soon: bool, badge: string}
     */
    public function describeMethod(string $method): array
    {
        if (! $this->hasMethod($method)) {
            throw new InvalidArgumentException('Unknown payment method.');
        }

        $label = (string) config("payments.methods.{$method}.label", $method);
        $gateway = (string) config("payments.methods.{$method}.gateway", 'stub');
        $offline = (bool) config("payments.methods.{$method}.offline", false);
        $live = $this->isLiveChargingConfigured($method);

        return [
            'key' => $method,
            'label' => $label,
            'gateway' => $gateway,
            'offline' => $offline,
            'live_charging' => $live,
            'coming_soon' => ! $live && ! $offline,
            'badge' => $live ? 'Available' : ($offline ? 'Offline' : 'Coming soon'),
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
        $gateway = config("payments.gateways.{$gatewayKey}");

        if (! is_array($gateway)) {
            return false;
        }

        return (bool) ($gateway['enabled'] ?? false)
            && (bool) ($gateway['live_charging'] ?? false);
    }

    public function default(): PaymentGatewayInterface
    {
        return $this->resolve((string) config('payments.default', 'stub'));
    }

    public function resolve(string $gatewayKey): PaymentGatewayInterface
    {
        $config = config("payments.gateways.{$gatewayKey}");

        if (! is_array($config)) {
            throw new InvalidArgumentException('Unknown payment gateway.');
        }

        // Live drivers are not registered in Phase 7A. Always use the stub
        // when live charging is not explicitly enabled.
        if (! ($config['enabled'] ?? false) || ! ($config['live_charging'] ?? false)) {
            return $this->container->make(StubPaymentGateway::class);
        }

        $driver = (string) ($config['driver'] ?? $gatewayKey);

        return match ($driver) {
            'stub' => $this->container->make(StubPaymentGateway::class),
            default => throw new InvalidArgumentException('Payment gateway driver is not available.'),
        };
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
     */
    public function initialize(Order $order, PaymentTransaction $transaction): GatewayInitializationResult
    {
        $method = (string) ($order->payment_method ?: '');

        if ($method === '' || ! $this->hasMethod($method)) {
            // Safe fallback — still non-charging.
            $gateway = $this->default();
        } else {
            $gateway = $this->resolveForMethod($method);
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
    }
}
