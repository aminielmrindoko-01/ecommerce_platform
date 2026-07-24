<?php

/**
 * |--------------------------------------------------------------------------
 * | Marketplace preference bootstrap
 * |--------------------------------------------------------------------------
 * | Runs early so every view can read locale/currency/country without
 * | re-resolving session/cookies. Also sets app locale and PHP timezone.
 */

namespace App\Http\Middleware;

use App\Support\Marketplace;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares marketplace preference variables with all Blade views.
 *
 * @package App\Http\Middleware
 */
class SetMarketplacePreferences
{
    /**
     * Resolve preferences, apply locale/timezone, share view globals, then continue.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = Marketplace::locale();
        $currency = Marketplace::currency();
        $country = Marketplace::country();

        app()->setLocale($locale);
        date_default_timezone_set(Marketplace::timezone());

        // Shared once per request so layouts/partials avoid repeated Marketplace calls.
        View::share([
            'mpLocale' => $locale,
            'mpCurrency' => $currency,
            'mpCountry' => $country,
            'mpLanguages' => Marketplace::languages(),
            'mpCurrencies' => Marketplace::currencies(),
            'mpCountries' => Marketplace::countries(),
            'mpShippingRegions' => Marketplace::shippingRegions(),
            'mpTaxRate' => Marketplace::taxRate(),
            'mpPhonePrefix' => Marketplace::countries()[$country]['phone'] ?? '+255',
        ]);

        return $next($request);
    }
}
