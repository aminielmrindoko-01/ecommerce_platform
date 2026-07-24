<?php

use App\Support\Marketplace;

if (! function_exists('money')) {
    function money(float|int|string|null $amountTzs): string
    {
        return Marketplace::money($amountTzs);
    }
}

if (! function_exists('mt')) {
    /** Marketplace translate helper (session locale). */
    function mt(string $key, ?string $fallback = null): string
    {
        return Marketplace::t($key, $fallback);
    }
}
