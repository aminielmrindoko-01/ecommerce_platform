<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Support\Payments\StubPaymentGateway;
use Illuminate\Support\ServiceProvider;

/**
 * Application service container bindings and boot hooks.
 *
 * Marketplace preference sharing is handled by SetMarketplacePreferences middleware
 * rather than View composers here.
 *
 * Event listeners under app/Listeners are auto-discovered by Laravel.
 *
 * @package App\Providers
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Default binding is the non-charging stub. Real gateways replace this later.
        $this->app->bind(PaymentGatewayInterface::class, StubPaymentGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
