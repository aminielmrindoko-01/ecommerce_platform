<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Services\PaymentGatewayManager;
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
        $this->app->singleton(PaymentGatewayManager::class);
        $this->app->singleton(StubPaymentGateway::class);

        // Default binding resolves through the manager (stub / coming-soon in Phase 7A).
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            return $app->make(PaymentGatewayManager::class)->default();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
