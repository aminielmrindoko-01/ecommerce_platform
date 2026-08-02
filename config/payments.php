<?php

/**
 * Payment gateway configuration (Phase 7A).
 *
 * Live charging is intentionally disabled. No API credentials are required.
 * Real provider drivers will be enabled only after credentials and webhook
 * verification are available in a later phase.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Default gateway key
    |--------------------------------------------------------------------------
    */
    'default' => env('PAYMENT_GATEWAY', 'stub'),

    /*
    |--------------------------------------------------------------------------
    | Supported storefront currency
    |--------------------------------------------------------------------------
    */
    'currency' => 'TZS',

    /*
    |--------------------------------------------------------------------------
    | Customer-facing payment methods
    |--------------------------------------------------------------------------
    |
    | Each method maps to a gateway key. Until live_charging is enabled for that
    | gateway, customers see a professional "coming soon" / offline experience
    | and payment_status remains pending.
    |
    */
    'methods' => [
        'mpesa' => [
            'label' => 'M-Pesa',
            'gateway' => 'mpesa',
            'offline' => false,
        ],
        'airtel' => [
            'label' => 'Airtel Money',
            'gateway' => 'airtel',
            'offline' => false,
        ],
        'tigo' => [
            'label' => 'Tigo Pesa',
            'gateway' => 'tigo',
            'offline' => false,
        ],
        'halopesa' => [
            'label' => 'HaloPesa',
            'gateway' => 'stub',
            'offline' => false,
        ],
        'mixx' => [
            'label' => 'Mixx by Yas',
            'gateway' => 'stub',
            'offline' => false,
        ],
        'mtn' => [
            'label' => 'MTN MoMo',
            'gateway' => 'stub',
            'offline' => false,
        ],
        'orange' => [
            'label' => 'Orange Money',
            'gateway' => 'stub',
            'offline' => false,
        ],
        'card' => [
            'label' => 'Card Payment',
            'gateway' => 'stripe',
            'offline' => false,
        ],
        'stripe' => [
            'label' => 'Stripe',
            'gateway' => 'stripe',
            'offline' => false,
        ],
        'paypal' => [
            'label' => 'PayPal',
            'gateway' => 'paypal',
            'offline' => false,
        ],
        'apple_pay' => [
            'label' => 'Apple Pay',
            'gateway' => 'stub',
            'offline' => false,
        ],
        'google_pay' => [
            'label' => 'Google Pay',
            'gateway' => 'stub',
            'offline' => false,
        ],
        'bank' => [
            'label' => 'Bank transfer',
            'gateway' => 'stub',
            'offline' => true,
        ],
        'cod' => [
            'label' => 'Cash on delivery',
            'gateway' => 'stub',
            'offline' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateway drivers
    |--------------------------------------------------------------------------
    |
    | enabled + live_charging must both be true before a real driver may charge.
    | Phase 7A keeps live_charging false for every gateway.
    |
    */
    'gateways' => [
        'stub' => [
            'driver' => 'stub',
            'enabled' => true,
            'live_charging' => false,
        ],
        'mpesa' => [
            'driver' => 'mpesa',
            'enabled' => false,
            'live_charging' => false,
        ],
        'airtel' => [
            'driver' => 'airtel',
            'enabled' => false,
            'live_charging' => false,
        ],
        'tigo' => [
            'driver' => 'tigo',
            'enabled' => false,
            'live_charging' => false,
        ],
        'stripe' => [
            'driver' => 'stripe',
            'enabled' => false,
            'live_charging' => false,
        ],
        'paypal' => [
            'driver' => 'paypal',
            'enabled' => false,
            'live_charging' => false,
        ],
    ],
];
