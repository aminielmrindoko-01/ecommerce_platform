<?php

/**
 * Enterprise RBAC configuration for SANA Market.
 *
 * Permissions use resource.action naming. Role maps are the source of truth
 * for seeding. Ownership checks remain mandatory for vendor/customer resources.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Permission catalog
    |--------------------------------------------------------------------------
    */
    'permissions' => [
        // Admin shell
        'admin.access',
        'dashboard.view',

        // Products
        'products.view',
        'products.create',
        'products.update',
        'products.delete',
        'products.publish',
        'products.unpublish',
        'products.manage_any',

        // Categories
        'categories.view',
        'categories.create',
        'categories.update',
        'categories.delete',

        // Inventory
        'inventory.view',
        'inventory.adjust',
        'inventory.history',

        // Orders
        'orders.view',
        'orders.update',
        'orders.cancel',
        'orders.refund',
        'orders.manage_any',

        // Customers
        'customers.view',
        'customers.update',
        'customers.suspend',

        // Vendors
        'vendors.view',
        'vendors.create',
        'vendors.update',
        'vendors.approve',
        'vendors.reject',
        'vendors.suspend',

        // Coupons
        'coupons.view',
        'coupons.create',
        'coupons.update',
        'coupons.delete',
        'coupons.activate',
        'coupons.deactivate',

        // Reviews
        'reviews.view',
        'reviews.create',
        'reviews.moderate',
        'reviews.approve',
        'reviews.reject',
        'reviews.hide',
        'reviews.restore',
        'reviews.flag',

        // Users / RBAC
        'users.view',
        'users.create',
        'users.update',
        'users.suspend',
        'roles.view',
        'roles.create',
        'roles.update',
        'roles.delete',
        'permissions.view',
        'permissions.assign',

        // Finance
        'payments.view',
        'payments.manage',
        'refunds.create',
        'payouts.view',
        'payouts.approve',
        'payouts.reject',
        'payouts.process',
        'transactions.view',
        'ledger.view',
        'finance.reports.view',
        'commission.manage',
        'settlement_holds.view',
        'settlement_holds.manage',

        // Marketplace operations (Phase 7)
        'returns.view',
        'returns.manage',
        'returns.approve',
        'disputes.view',
        'disputes.respond',
        'disputes.resolve',
        'disputes.manage',
        'chargebacks.view',
        'chargebacks.create',
        'chargebacks.manage',
        'chargebacks.resolve',

        // Audit / security
        'audit_logs.view',
        'security_events.view',

        // System
        'settings.view',
        'settings.update',

        // Customer self-service
        'wishlist.view',
        'wishlist.manage',
        'addresses.view',
        'addresses.manage',
        'profile.view',
        'profile.update',

        // Vendor hub
        'vendor.access',
    ],

    /*
    |--------------------------------------------------------------------------
    | Role → permission map
    |--------------------------------------------------------------------------
    |
    | Role names are stable slugs. Display names live in the roles table seeder.
    |
    */
    'roles' => [
        'super_admin' => ['*'],

        'admin' => [
            'admin.access', 'dashboard.view',
            'products.view', 'products.create', 'products.update', 'products.delete', 'products.publish', 'products.unpublish', 'products.manage_any',
            'categories.view', 'categories.create', 'categories.update', 'categories.delete',
            'inventory.view', 'inventory.adjust', 'inventory.history',
            'orders.view', 'orders.update', 'orders.cancel', 'orders.manage_any',
            'customers.view', 'customers.update', 'customers.suspend',
            'vendors.view', 'vendors.create', 'vendors.update', 'vendors.approve', 'vendors.reject', 'vendors.suspend',
            'coupons.view', 'coupons.create', 'coupons.update', 'coupons.delete', 'coupons.activate', 'coupons.deactivate',
            'reviews.view', 'reviews.moderate', 'reviews.approve', 'reviews.reject', 'reviews.hide', 'reviews.restore', 'reviews.flag',
            'users.view', 'users.create', 'users.update', 'users.suspend',
            'payments.view', 'payments.manage', 'transactions.view', 'refunds.create',
            'payouts.view', 'payouts.approve', 'ledger.view', 'finance.reports.view',
            'returns.view', 'returns.manage', 'returns.approve',
            'disputes.view', 'disputes.respond', 'disputes.resolve', 'disputes.manage',
            'chargebacks.view', 'chargebacks.create', 'chargebacks.manage',
            'settlement_holds.view', 'settlement_holds.manage',
            'audit_logs.view',
            'settings.view',
        ],

        'product_manager' => [
            'admin.access', 'dashboard.view',
            'products.view', 'products.create', 'products.update', 'products.publish', 'products.unpublish', 'products.manage_any',
            'categories.view', 'categories.create', 'categories.update',
            'inventory.view',
        ],

        'inventory_manager' => [
            'admin.access', 'dashboard.view',
            'inventory.view', 'inventory.adjust', 'inventory.history',
            'products.view',
        ],

        'order_manager' => [
            'admin.access', 'dashboard.view',
            'orders.view', 'orders.update', 'orders.cancel', 'orders.manage_any',
            'products.view', 'inventory.view',
            'customers.view',
            'payments.view',
            'returns.view', 'returns.approve',
            'disputes.view', 'disputes.respond',
        ],

        'customer_support' => [
            'admin.access', 'dashboard.view',
            'customers.view', 'customers.update',
            'orders.view',
            'reviews.view',
            'payments.view',
            'returns.view', 'returns.approve',
            'disputes.view', 'disputes.respond', 'disputes.resolve',
            'chargebacks.view',
            'settlement_holds.view',
        ],

        'vendor_manager' => [
            'admin.access', 'dashboard.view',
            'vendors.view', 'vendors.create', 'vendors.update', 'vendors.approve', 'vendors.reject', 'vendors.suspend',
            'products.view',
        ],

        'marketing_manager' => [
            'admin.access', 'dashboard.view',
            'coupons.view', 'coupons.create', 'coupons.update', 'coupons.delete', 'coupons.activate', 'coupons.deactivate',
            'products.view', 'categories.view',
        ],

        'review_moderator' => [
            'admin.access', 'dashboard.view',
            'reviews.view', 'reviews.moderate', 'reviews.approve', 'reviews.reject', 'reviews.hide', 'reviews.restore', 'reviews.flag',
            'products.view',
        ],

        'finance_manager' => [
            'admin.access', 'dashboard.view',
            'payments.view', 'payments.manage', 'transactions.view', 'refunds.create',
            'payouts.view', 'payouts.approve', 'payouts.reject', 'payouts.process',
            'ledger.view', 'finance.reports.view',
            'commission.manage',
            'settlement_holds.view', 'settlement_holds.manage',
            'chargebacks.view', 'chargebacks.create', 'chargebacks.manage', 'chargebacks.resolve',
            'returns.view',
            'orders.view',
        ],

        'auditor' => [
            'admin.access', 'dashboard.view',
            'audit_logs.view', 'security_events.view',
            'orders.view', 'products.view', 'vendors.view', 'customers.view',
            'payments.view', 'transactions.view', 'ledger.view', 'finance.reports.view', 'payouts.view',
            'returns.view', 'disputes.view', 'chargebacks.view', 'settlement_holds.view',
            'reviews.view',
        ],

        'vendor' => [
            'vendor.access',
            'products.view', 'products.create', 'products.update', 'products.delete',
            'inventory.view', 'inventory.adjust',
            'orders.view', 'orders.update',
            'reviews.view',
            'profile.view', 'profile.update',
        ],

        'customer' => [
            'orders.view',
            'wishlist.view', 'wishlist.manage',
            'reviews.create', 'reviews.view',
            'addresses.view', 'addresses.manage',
            'profile.view', 'profile.update',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy users.role → RBAC role bridge
    |--------------------------------------------------------------------------
    |
    | users.role is marketplace identity only. When a user has no user_roles
    | rows, the resolver may MATERIALIZE the mapped RBAC role into user_roles
    | (once). After that, ONLY RBAC roles grant permissions — users.role can
    | never override or widen RBAC.
    |
    */
    'legacy_role_map' => [
        'admin' => 'super_admin',
        'vendor' => 'vendor',
        'customer' => 'customer',
    ],

    /*
    |--------------------------------------------------------------------------
    | MFA (TOTP) for privileged roles
    |--------------------------------------------------------------------------
    */
    'mfa' => [
        'issuer' => env('MFA_ISSUER', 'SANA Market'),
        'required_roles' => [
            'super_admin',
            'admin',
            'finance_manager',
            'vendor_manager',
        ],
        // When true, privileged roles without MFA enrollment are blocked from admin shell.
        'enforce_enrollment' => env('MFA_ENFORCE_ENROLLMENT', false),
        'recovery_codes_count' => 8,
        'window' => 1, // ±1 time-step tolerance
    ],

    /*
    |--------------------------------------------------------------------------
    | Step-up authentication
    |--------------------------------------------------------------------------
    */
    'step_up' => [
        'ttl_seconds' => (int) env('STEP_UP_TTL_SECONDS', 300),
        'password_confirmation_required' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles that may assign other roles / permissions
    |--------------------------------------------------------------------------
    */
    'privileged_roles' => [
        'super_admin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles that ordinary admins cannot assign
    |--------------------------------------------------------------------------
    */
    'protected_roles' => [
        'super_admin',
    ],
];
