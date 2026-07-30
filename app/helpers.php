<?php

/**
 * Global Blade/controller helpers that thin-wrap App\Support\Marketplace.
 * Loaded via composer.json "files" autoload — keep these side-effect free.
 */

use App\Support\Marketplace;

if (! function_exists('money')) {
    /**
     * Format a TZS amount using the visitor's preferred display currency.
     *
     * @param  float|int|string|null  $amountTzs
     */
    function money(float|int|string|null $amountTzs): string
    {
        return Marketplace::money($amountTzs);
    }
}

if (! function_exists('mt')) {
    /**
     * Marketplace translate helper (session/cookie locale via Marketplace::t).
     *
     * Named `mt` to avoid colliding with Laravel's `__` / `trans` helpers.
     */
    function mt(string $key, ?string $fallback = null): string
    {
        return Marketplace::t($key, $fallback);
    }
}
