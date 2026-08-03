<?php

/**
 * Marketplace finance / commission configuration (Phase 6).
 *
 * PAYOUT INTEGRATION STATUS: SANDBOX / NOT PRODUCTION-READY
 */

return [

    'currency' => env('FINANCE_CURRENCY', env('PAYMENT_CURRENCY', 'TZS')),

    /*
    |--------------------------------------------------------------------------
    | Default commission (snapshot at entitlement time)
    |--------------------------------------------------------------------------
    */
    'commission' => [
        'type' => env('FINANCE_COMMISSION_TYPE', 'percentage'), // percentage|fixed
        'rate' => env('FINANCE_COMMISSION_RATE', '0.10'), // 10%
        'fixed_amount' => env('FINANCE_COMMISSION_FIXED', '0.00'),
        // Commission applies to order-item line totals (price × qty), not tax/shipping.
        'basis' => 'item_subtotal',
    ],

    /*
    |--------------------------------------------------------------------------
    | Settlement hold before entitlement becomes payable
    |--------------------------------------------------------------------------
    | Hours after payment before funds are available for payout.
    | 0 = immediately available (still ledger-tracked).
    */
    'settlement_hold_hours' => (int) env('FINANCE_SETTLEMENT_HOLD_HOURS', 0),

    /*
    |--------------------------------------------------------------------------
    | Payout gateway
    |--------------------------------------------------------------------------
    */
    'payout' => [
        'default' => env('PAYOUT_GATEWAY', 'stub'),
        'gateways' => [
            'stub' => [
                'driver' => 'stub',
                'enabled' => true,
                'live_payouts' => false,
                'display_name' => 'Stub / Sandbox Payout',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Separation of duties (soft policy for payouts)
    |--------------------------------------------------------------------------
    | When true, the same user cannot both approve and process the same payout.
    */
    'payout_separation_of_duties' => (bool) env('FINANCE_PAYOUT_SOD', true),
];
