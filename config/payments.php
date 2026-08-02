<?php

/**
 * Payment gateway configuration (Phase 7B).
 *
 * Live charging is intentionally disabled. No API credentials are required.
 * Real provider drivers will be enabled only after credentials and webhook
 * verification are available in a later phase.
 *
 * Fail closed: if a live gateway is misconfigured, resolution falls back to
 * the non-charging stub and payment remains pending.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Active / default gateway key
    |--------------------------------------------------------------------------
    */
    'default' => env('PAYMENT_GATEWAY', 'stub'),

    /*
    |--------------------------------------------------------------------------
    | Supported storefront currencies
    |--------------------------------------------------------------------------
    */
    'currency' => env('PAYMENT_CURRENCY', 'TZS'),

    'currencies' => [
        'TZS',
    ],

    /*
    |--------------------------------------------------------------------------
    | Customer-facing payment methods
    |--------------------------------------------------------------------------
    */
    'methods' => [
        'mpesa' => [
            'label' => 'M-Pesa',
            'gateway' => 'mpesa',
            'offline' => false,
            'group' => 'online',
        ],
        'airtel' => [
            'label' => 'Airtel Money',
            'gateway' => 'airtel',
            'offline' => false,
            'group' => 'online',
        ],
        'tigo' => [
            'label' => 'Tigo Pesa',
            'gateway' => 'tigo',
            'offline' => false,
            'group' => 'online',
        ],
        'halopesa' => [
            'label' => 'HaloPesa',
            'gateway' => 'stub',
            'offline' => false,
            'group' => 'online',
        ],
        'mixx' => [
            'label' => 'Mixx by Yas',
            'gateway' => 'stub',
            'offline' => false,
            'group' => 'online',
        ],
        'mtn' => [
            'label' => 'MTN MoMo',
            'gateway' => 'stub',
            'offline' => false,
            'group' => 'online',
        ],
        'orange' => [
            'label' => 'Orange Money',
            'gateway' => 'stub',
            'offline' => false,
            'group' => 'online',
        ],
        'card' => [
            'label' => 'Card Payment',
            'gateway' => 'stripe',
            'offline' => false,
            'group' => 'online',
        ],
        'stripe' => [
            'label' => 'Stripe',
            'gateway' => 'stripe',
            'offline' => false,
            'group' => 'online',
        ],
        'paypal' => [
            'label' => 'PayPal',
            'gateway' => 'paypal',
            'offline' => false,
            'group' => 'online',
        ],
        'apple_pay' => [
            'label' => 'Apple Pay',
            'gateway' => 'stub',
            'offline' => false,
            'group' => 'online',
        ],
        'google_pay' => [
            'label' => 'Google Pay',
            'gateway' => 'stub',
            'offline' => false,
            'group' => 'online',
        ],
        'bank' => [
            'label' => 'Bank transfer',
            'gateway' => 'stub',
            'offline' => true,
            'group' => 'offline',
        ],
        'cod' => [
            'label' => 'Cash on delivery',
            'gateway' => 'stub',
            'offline' => true,
            'group' => 'offline',
        ],
        'pesapal' => [
            'label' => 'PesaPal',
            'gateway' => 'pesapal',
            'offline' => false,
            'group' => 'online',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateway drivers
    |--------------------------------------------------------------------------
    |
    | enabled + live_charging must both be true before a real driver may charge.
    | Secrets must live only in environment variables — never in this file.
    |
    | Future drivers (not implemented): mpesa, airtel, tigo, stripe, paypal.
    |
    */
    'gateways' => [
        'stub' => [
            'driver' => 'stub',
            'name' => 'Offline Payment',
            'display_name' => 'Stub / Offline / Coming Soon',
            'enabled' => true,
            'live_charging' => false,
            'coming_soon' => true,
            'availability' => 'coming_soon',
        ],
        'mpesa' => [
            'driver' => 'mpesa',
            'name' => 'M-Pesa',
            'display_name' => 'M-Pesa',
            'enabled' => env('PAYMENT_MPESA_ENABLED', false),
            'live_charging' => env('PAYMENT_MPESA_LIVE_CHARGING', false),
            'coming_soon' => true,
            'availability' => 'coming_soon',
        ],
        'airtel' => [
            'driver' => 'airtel',
            'name' => 'Airtel Money',
            'display_name' => 'Airtel Money',
            'enabled' => env('PAYMENT_AIRTEL_ENABLED', false),
            'live_charging' => env('PAYMENT_AIRTEL_LIVE_CHARGING', false),
            'coming_soon' => true,
            'availability' => 'coming_soon',
        ],
        'tigo' => [
            'driver' => 'tigo',
            'name' => 'Tigo Pesa',
            'display_name' => 'Tigo Pesa',
            'enabled' => env('PAYMENT_TIGO_ENABLED', false),
            'live_charging' => env('PAYMENT_TIGO_LIVE_CHARGING', false),
            'coming_soon' => true,
            'availability' => 'coming_soon',
        ],
        'stripe' => [
            'driver' => 'stripe',
            'name' => 'Stripe',
            'display_name' => 'Card (Stripe)',
            'enabled' => env('PAYMENT_STRIPE_ENABLED', false),
            'live_charging' => env('PAYMENT_STRIPE_LIVE_CHARGING', false),
            'coming_soon' => true,
            'availability' => 'coming_soon',
        ],
        'paypal' => [
            'driver' => 'paypal',
            'name' => 'PayPal',
            'display_name' => 'PayPal',
            'enabled' => env('PAYMENT_PAYPAL_ENABLED', false),
            'live_charging' => env('PAYMENT_PAYPAL_LIVE_CHARGING', false),
            'coming_soon' => true,
            'availability' => 'coming_soon',
        ],
        'pesapal' => [
            'driver' => 'pesapal',
            'name' => 'PesaPal',
            'display_name' => 'PesaPal (Sandbox)',
            'enabled' => env('PAYMENT_PESAPAL_ENABLED', false),
            // Sandbox charging only — NOT production money movement.
            'live_charging' => env('PAYMENT_PESAPAL_SANDBOX_CHARGING', false),
            'coming_soon' => true,
            'availability' => 'coming_soon',
            // Phase 8C: only "sandbox" is permitted. Any other value fail-closes.
            'environment' => env('PESAPAL_ENV', 'sandbox'),
            'consumer_key' => env('PESAPAL_CONSUMER_KEY'),
            'consumer_secret' => env('PESAPAL_CONSUMER_SECRET'),
            'callback_url' => env('PESAPAL_CALLBACK_URL'),
            'ipn_url' => env('PESAPAL_IPN_URL'),
            // Optional pre-registered sandbox IPN id (avoids RegisterIPN when set).
            'ipn_id' => env('PESAPAL_IPN_ID'),
            'timeout' => (int) env('PESAPAL_TIMEOUT', 15),
            'base_urls' => [
                'sandbox' => 'https://cybqa.pesapal.com/pesapalv3',
                // Production URL intentionally unused in Phase 8A/8B/8C.
            ],
            // Only these hosts may appear in "Continue to PesaPal" links.
            'allowed_redirect_hosts' => [
                'cybqa.pesapal.com',
            ],
        ],
    ],
];
