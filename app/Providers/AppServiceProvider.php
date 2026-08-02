<?php

namespace App\Providers;

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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
