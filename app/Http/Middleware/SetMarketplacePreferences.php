<?php

namespace App\Http\Middleware;

use App\Support\Marketplace;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetMarketplacePreferences
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = Marketplace::locale();
        $currency = Marketplace::currency();
        $country = Marketplace::country();

        app()->setLocale($locale);
        date_default_timezone_set(Marketplace::timezone());

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
