<?php

/**
 * |--------------------------------------------------------------------------
 * | Marketplace preferences & localization
 * |--------------------------------------------------------------------------
 * | Central registry for locale, currency display conversion, country tax,
 * | and shipping region metadata. Session values win over cookies; both are
 * | validated against allow-lists so unknown codes fall back safely.
 */

namespace App\Support;

/**
 * Static helpers for SANA Market multi-region UX.
 *
 * Catalog prices are stored and calculated in TZS. Display currencies use
 * approximate fixed rates (not live FX) so UI formatting stays deterministic.
 *
 * @package App\Support
 */
class Marketplace
{
    /** Base catalog currency is TZS (Tanzanian Shilling). */
    public const BASE_CURRENCY = 'TZS';

    /**
     * Supported UI languages keyed by ISO-ish locale codes stored in session/cookie.
     *
     * @return array<string, array{label: string, native: string}>
     */
    public static function languages(): array
    {
        return [
            'en' => ['label' => 'English', 'native' => 'English'],
            'sw' => ['label' => 'Swahili', 'native' => 'Kiswahili'],
            'fr' => ['label' => 'French', 'native' => 'Français'],
        ];
    }

    /**
     * Display currencies and conversion metadata relative to BASE_CURRENCY.
     *
     * Rates are approximate display multipliers only — order totals remain TZS.
     *
     * @return array<string, array{symbol: string, rate: float, decimals: int, label: string}>
     */
    public static function currencies(): array
    {
        // Approximate display rates vs TZS for UI conversion (not live FX).
        return [
            'TZS' => ['symbol' => 'TSh', 'rate' => 1, 'decimals' => 0, 'label' => 'Tanzanian Shilling'],
            'USD' => ['symbol' => '$', 'rate' => 1 / 2600, 'decimals' => 2, 'label' => 'US Dollar'],
            'EUR' => ['symbol' => '€', 'rate' => 1 / 2800, 'decimals' => 2, 'label' => 'Euro'],
            'KES' => ['symbol' => 'KSh', 'rate' => 1 / 20, 'decimals' => 0, 'label' => 'Kenyan Shilling'],
            'UGX' => ['symbol' => 'USh', 'rate' => 1 / 0.7, 'decimals' => 0, 'label' => 'Ugandan Shilling'],
            'GBP' => ['symbol' => '£', 'rate' => 1 / 3300, 'decimals' => 2, 'label' => 'British Pound'],
        ];
    }

    /**
     * Ship-to countries with VAT/tax rate and shipping region bucket.
     *
     * Tax rate drives cart/checkout tax line; shipping key maps to shippingRegions().
     *
     * @return array<string, array{name: string, phone: string, timezone: string, tax: float, shipping: string}>
     */
    public static function countries(): array
    {
        return [
            'TZ' => ['name' => 'Tanzania', 'phone' => '+255', 'timezone' => 'Africa/Dar_es_Salaam', 'tax' => 0.18, 'shipping' => 'east_africa'],
            'KE' => ['name' => 'Kenya', 'phone' => '+254', 'timezone' => 'Africa/Nairobi', 'tax' => 0.16, 'shipping' => 'east_africa'],
            'UG' => ['name' => 'Uganda', 'phone' => '+256', 'timezone' => 'Africa/Kampala', 'tax' => 0.18, 'shipping' => 'east_africa'],
            'RW' => ['name' => 'Rwanda', 'phone' => '+250', 'timezone' => 'Africa/Kigali', 'tax' => 0.18, 'shipping' => 'east_africa'],
            'NG' => ['name' => 'Nigeria', 'phone' => '+234', 'timezone' => 'Africa/Lagos', 'tax' => 0.075, 'shipping' => 'west_africa'],
            'GH' => ['name' => 'Ghana', 'phone' => '+233', 'timezone' => 'Africa/Accra', 'tax' => 0.125, 'shipping' => 'west_africa'],
            'ZA' => ['name' => 'South Africa', 'phone' => '+27', 'timezone' => 'Africa/Johannesburg', 'tax' => 0.15, 'shipping' => 'southern_africa'],
            'US' => ['name' => 'United States', 'phone' => '+1', 'timezone' => 'America/New_York', 'tax' => 0.08, 'shipping' => 'international'],
            'GB' => ['name' => 'United Kingdom', 'phone' => '+44', 'timezone' => 'Europe/London', 'tax' => 0.20, 'shipping' => 'international'],
        ];
    }

    /**
     * Human-readable shipping ETA copy keyed by country shipping bucket.
     *
     * @return array<string, string>
     */
    public static function shippingRegions(): array
    {
        return [
            'east_africa' => 'East Africa (3–7 days)',
            'west_africa' => 'West Africa (5–12 days)',
            'southern_africa' => 'Southern Africa (5–10 days)',
            'international' => 'International (7–21 days)',
        ];
    }

    /**
     * Active UI locale from session, then cookie, then English default.
     */
    public static function locale(): string
    {
        $locale = (string) (session('locale') ?? request()->cookie('sana_locale', 'en'));

        return array_key_exists($locale, self::languages()) ? $locale : 'en';
    }

    /**
     * Active display currency code (allow-listed).
     */
    public static function currency(): string
    {
        $currency = (string) (session('currency') ?? request()->cookie('sana_currency', self::BASE_CURRENCY));

        return array_key_exists($currency, self::currencies()) ? $currency : self::BASE_CURRENCY;
    }

    /**
     * Active ship-to country code (defaults to Tanzania).
     */
    public static function country(): string
    {
        $country = (string) (session('country') ?? request()->cookie('sana_country', 'TZ'));

        return array_key_exists($country, self::countries()) ? $country : 'TZ';
    }

    /**
     * PHP timezone for the active country (used by preference middleware).
     */
    public static function timezone(): string
    {
        return self::countries()[self::country()]['timezone'] ?? 'Africa/Dar_es_Salaam';
    }

    /**
     * Country VAT/sales tax rate applied to (subtotal − discount) at cart/checkout.
     */
    public static function taxRate(): float
    {
        return (float) (self::countries()[self::country()]['tax'] ?? 0.18);
    }

    /**
     * Format a TZS catalog amount in the visitor's display currency.
     *
     * @param  float|int|string|null  $amountTzs  Amount in base catalog currency
     */
    public static function money(float|int|string|null $amountTzs): string
    {
        $amount = (float) ($amountTzs ?? 0);
        $code = self::currency();
        $meta = self::currencies()[$code];
        $converted = $amount * $meta['rate'];

        return $meta['symbol'].' '.number_format($converted, $meta['decimals']);
    }

    /**
     * Resolve a marketplace UI string for the active locale (falls back to English / key).
     *
     * @param  string  $key  Dot-key such as nav.cart
     * @param  string|null  $fallback  Optional override when key is missing
     */
    public static function t(string $key, ?string $fallback = null): string
    {
        $dict = self::dictionary(self::locale());

        return $dict[$key] ?? ($fallback ?? $key);
    }

    /**
     * Lightweight in-code translation dictionary (not Laravel lang files).
     *
     * Non-English locales merge over English so missing keys still resolve.
     *
     * @return array<string, string>
     */
    public static function dictionary(string $locale): array
    {
        $en = [
            'nav.categories' => 'Categories',
            'nav.deals' => 'Deals',
            'nav.sellers' => 'Sellers',
            'nav.wishlist' => 'Wishlist',
            'nav.account' => 'Account',
            'nav.admin' => 'Admin',
            'nav.login' => 'Login',
            'nav.join' => 'Join',
            'nav.logout' => 'Logout',
            'nav.cart' => 'Cart',
            'search.placeholder' => 'Search phones, fashion, home…',
            'search.button' => 'Search',
            'cta.add_to_cart' => 'Add to cart',
            'cta.out_of_stock' => 'Out of stock',
            'topbar.trust' => 'Free delivery on orders over TSh 150,000 · Secure checkout · Buyer protection',
            'footer.tagline' => 'East Africa’s marketplace for trusted sellers — phones, fashion, home, and everyday essentials with secure payments and fast delivery.',
            'footer.shop' => 'Shop',
            'footer.help' => 'Help',
            'footer.trust' => 'Trust',
            'badge.new' => 'New',
            'checkout.title' => 'Checkout',
            'cart.title' => 'Your cart',
        ];

        $sw = [
            'nav.categories' => 'Kategoria',
            'nav.deals' => 'Ofa',
            'nav.sellers' => 'Wauzaji',
            'nav.wishlist' => 'Orodha ya matamanio',
            'nav.account' => 'Akaunti',
            'nav.admin' => 'Admin',
            'nav.login' => 'Ingia',
            'nav.join' => 'Jiunge',
            'nav.logout' => 'Toka',
            'nav.cart' => 'Kikapu',
            'search.placeholder' => 'Tafuta simu, mitindo, nyumbani…',
            'search.button' => 'Tafuta',
            'cta.add_to_cart' => 'Ongeza kikapuni',
            'cta.out_of_stock' => 'Haipatikani',
            'topbar.trust' => 'Usafirishaji bure kwa oda zaidi ya TSh 150,000 · Malipo salama · Ulinzi wa mnunuzi',
            'footer.tagline' => 'Soko la Afrika Mashariki kwa wauzaji wanaoaminika — simu, mitindo, nyumbani, na mahitaji ya kila siku.',
            'footer.shop' => 'Duka',
            'footer.help' => 'Msaada',
            'footer.trust' => 'Uaminifu',
            'badge.new' => 'Mpya',
            'checkout.title' => 'Malipo',
            'cart.title' => 'Kikapu chako',
        ];

        $fr = [
            'nav.categories' => 'Catégories',
            'nav.deals' => 'Promos',
            'nav.sellers' => 'Vendeurs',
            'nav.wishlist' => 'Favoris',
            'nav.account' => 'Compte',
            'nav.admin' => 'Admin',
            'nav.login' => 'Connexion',
            'nav.join' => 'S’inscrire',
            'nav.logout' => 'Déconnexion',
            'nav.cart' => 'Panier',
            'search.placeholder' => 'Rechercher téléphones, mode, maison…',
            'search.button' => 'Rechercher',
            'cta.add_to_cart' => 'Ajouter au panier',
            'cta.out_of_stock' => 'Rupture de stock',
            'topbar.trust' => 'Livraison gratuite dès TSh 150 000 · Paiement sécurisé · Protection acheteur',
            'footer.tagline' => 'La marketplace d’Afrique de l’Est pour des vendeurs de confiance.',
            'footer.shop' => 'Boutique',
            'footer.help' => 'Aide',
            'footer.trust' => 'Confiance',
            'badge.new' => 'Nouveau',
            'checkout.title' => 'Paiement',
            'cart.title' => 'Votre panier',
        ];

        return match ($locale) {
            'sw' => array_merge($en, $sw),
            'fr' => array_merge($en, $fr),
            default => $en,
        };
    }
}
