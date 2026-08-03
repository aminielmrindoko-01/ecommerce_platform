<?php

/**
 * Marketplace post-purchase operations (Phase 7).
 *
 * CHARGEBACK INTEGRATION: INTERNAL ARCHITECTURE / NOT PROVIDER-CONNECTED
 */

return [

    'currency' => env('FINANCE_CURRENCY', env('PAYMENT_CURRENCY', 'TZS')),

    /*
    |--------------------------------------------------------------------------
    | Returns
    |--------------------------------------------------------------------------
    */
    'returns' => [
        // Customer may request a return within N days of item delivery (or order delivered).
        'window_days' => (int) env('RETURN_WINDOW_DAYS', 14),
        // Allowed item fulfillment statuses for return eligibility.
        'eligible_fulfillment_statuses' => ['delivered'],
        // Order-level statuses that block returns.
        'blocked_order_statuses' => ['cancelled', 'pending'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Settlement holds
    |--------------------------------------------------------------------------
    | In addition to FINANCE_SETTLEMENT_HOLD_HOURS on entitlements, explicit
    | settlement_holds rows freeze payable for returns/disputes/chargebacks.
    */
    'holds' => [
        'auto_hold_on_return' => true,
        'auto_hold_on_dispute' => true,
        'auto_hold_on_chargeback' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Chargebacks
    |--------------------------------------------------------------------------
    */
    'chargebacks' => [
        'provider' => env('CHARGEBACK_PROVIDER', 'internal'),
        'live' => false,
    ],
];
