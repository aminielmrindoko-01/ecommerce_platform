<?php

/**
 * |--------------------------------------------------------------------------
 * | Locale / currency / country preferences
 * |--------------------------------------------------------------------------
 * | Writes allow-listed values to session and long-lived cookies so
 * | SetMarketplacePreferences can restore them across visits.
 */

namespace App\Http\Controllers;

use App\Support\Marketplace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Updates visitor marketplace preferences from the topbar form.
 *
 * @package App\Http\Controllers
 */
class PreferenceController extends Controller
{
    /**
     * Validate and persist selected locale, currency, and/or country.
     *
     * Cookies expire in ~1 year; session is updated immediately for the next request.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => 'nullable|in:'.implode(',', array_keys(Marketplace::languages())),
            'currency' => 'nullable|in:'.implode(',', array_keys(Marketplace::currencies())),
            'country' => 'nullable|in:'.implode(',', array_keys(Marketplace::countries())),
        ]);

        $cookies = [];

        if (! empty($data['locale'])) {
            session(['locale' => $data['locale']]);
            $cookies[] = cookie('sana_locale', $data['locale'], 60 * 24 * 365);
        }

        if (! empty($data['currency'])) {
            session(['currency' => $data['currency']]);
            $cookies[] = cookie('sana_currency', $data['currency'], 60 * 24 * 365);
        }

        if (! empty($data['country'])) {
            session(['country' => $data['country']]);
            $cookies[] = cookie('sana_country', $data['country'], 60 * 24 * 365);
        }

        $redirect = redirect()->back()->with('success', 'Preferences updated.');

        foreach ($cookies as $cookie) {
            $redirect->withCookie($cookie);
        }

        return $redirect;
    }
}
