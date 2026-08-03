# SANA Market — E-Commerce Marketplace

Laravel marketplace for browsing products, managing a session cart, placing orders, and administering catalog/order operations from an admin console. Vendors manage their own stores and products through a dedicated seller hub.

## Overview

SANA Market is a multi-vendor storefront focused on East African shopping preferences (locale, currency display, country tax). Customers can register, browse the catalog, manage a cart, check out, and track their orders. Vendors manage their own products and view orders containing their items. Admins retain global marketplace control.

## Features

* Customer authentication (register, login, logout, password change)
* Role-based access (`customer`, `vendor`, `admin`) with `admin` and `vendor` middleware
* Multi-vendor marketplace with ownership via `vendors.user_id` → `products.vendor_id`
* Vendor dashboard, product CRUD, order isolation, and store profile
* Admin global product create/update/delete and vendor verification
* Session shopping cart with stock clamping and live price sync
* Checkout with server-side price/stock validation (payment gateways stubbed)
* Order history with ownership checks (IDOR protection)
* Admin dashboard (KPIs, orders, users, vendors, inventory, coupons, reviews)
* Localization, currency display conversion, and country preference cookies
* SEO helpers (sitemap, robots, Open Graph, JSON-LD)
* Secure image uploads (`jpg`, `jpeg`, `png`, `webp`, `gif`)
* PHPUnit feature tests for auth, authorization, cart, checkout, and vendor IDOR

## Vendor Marketplace

* Each vendor store is linked to exactly one user (`vendors.user_id`, unique FK)
* Vendors access `/vendor` (dashboard, products, orders, profile)
* Product create/update/delete is ownership-scoped: `products.vendor_id` must match the authenticated vendor
* `vendor_id` is never accepted from vendor forms — the server assigns ownership
* Vendor order views show only that vendor’s line items and a vendor subtotal (not the full multi-vendor order total)
* Customers cannot access vendor routes; admins use `/admin` instead of the seller hub

## Multi-Vendor Fulfillment

Order payment/admin lifecycle and vendor fulfillment are intentionally separate:

| Field | Purpose | Values |
|-------|---------|--------|
| `orders.status` | Overall order / payment / admin lifecycle | `pending`, `paid`, `shipped`, `completed` |
| `order_items.fulfillment_status` | Per-vendor line-item fulfillment | `pending`, `confirmed`, `processing`, `shipped`, `delivered`, `cancelled` |

* There is **no** `orders.vendor_id`. Ownership is derived from `order_item → product.vendor_id → vendor.user_id`.
* Vendors update fulfillment only for their own line items via `PATCH /vendor/orders/{order}/items/{orderItem}/fulfillment`.
* Transitions are enforced by `OrderItemFulfillmentService` (e.g. `pending → confirmed → processing → shipped → delivered`; cancel from `pending` or `confirmed` only).
* Customers can view fulfillment status on their order page (grouped by vendor) but cannot change it.
* Admins see order status and each item’s fulfillment status in `/admin/orders`.
* Vendor dashboards show fulfillment KPI counts for their own items only.
* Cross-vendor IDOR attempts return `403`; price, quantity, product, and order ownership fields are not mass-assignable from fulfillment requests.

## Marketplace Operations

Operational workflows built on top of per-item fulfillment:

* **Database notifications** — customers receive fulfillment updates; vendors receive new-order and cancellation alerts (`notifications` table, account inbox at `/account/notifications`)
* **Fulfillment audit history** — every successful status change writes `fulfillment_status_histories` (actor, role, from/to, optional reason)
* **Concurrency-safe transitions** — `OrderItemFulfillmentService` uses transactions + `lockForUpdate()` before applying changes
* **Admin overrides** — admins may use an explicit broader transition map via `PATCH /admin/orders/{order}/items/{orderItem}/fulfillment` (reason required for reopen and late cancellations)
* **Dynamic fulfillment summary** — `OrderFulfillmentSummary` computes display-only aggregate state from line items (not stored; separate from `orders.status`)
* **Customer tracking** — order detail shows vendor grouping, progress steps, and the computed fulfillment summary
* **Vendor Needs Action** — dashboard queue for pending/confirmed/processing items; order list supports `?fulfillment=` filtering

Email/SMS delivery, payment gateways, payouts, and shipping-provider APIs remain out of scope.

## Payment Operations Foundation

Payment charging is **not** live. Phase 5–6 add a secure operations layer for recording and administering payment state:

* `orders.payment_status` — dedicated payment lifecycle (`pending`, `processing`, `paid`, `failed`, `cancelled`, `refunded`, `partially_refunded`)
* `orders.status` — coarse admin/order lifecycle only (`pending`, `shipped`, `completed`; `paid` is set via `PaymentService` soft-sync, not the legacy status endpoint)
* `payment_transactions` — order-level records (`manual` / `stub` providers only) with unique `reference` and unique `provider_reference`
* `PaymentService` — central state machine with `lockForUpdate()`, decimal-safe (`bccomp`/`bcadd`) amount/currency checks against `order.total_price`, strict provider-reference conflict handling, and audit history
* Admin payment updates via `PATCH /admin/orders/{order}/payment` (auth + admin + policy + FormRequest)
* Customer-visible payment status/reference on own orders only
* Checkout initializes `payment_status=pending` and a stub pending transaction
* Checkout idempotency uses DB-backed one-time tokens (`checkout_idempotency_keys`) consumed atomically with `lockForUpdate()` inside the order transaction
* `PaymentGatewayInterface` + `StubPaymentGateway` — architecture readiness only (no live charging)
* Database notifications for payment successful / failed / cancelled (dispatched after commit)

Vendors cannot mutate payments. Live M-Pesa/card/PayPal charging, webhooks, payouts, commissions, wallets, and full refund workflows remain out of scope.

### Future webhook requirements (not implemented)

When real gateways are added, webhooks must include: signature verification, provider reference validation, replay protection, idempotency, amount/currency verification, order/payment lookup under row locks, audit history, and after-commit notifications. Browser-submitted payment success must never be trusted.

## Phase 7A — Gateway Ready / Coming Soon

Live payment charging is intentionally disabled.
M-Pesa, Airtel Money, Tigo Pesa, card and PayPal integrations
will be added only after the appropriate provider credentials
and webhook verification requirements are available.

Phase 7A adds:

* `config/payments.php` — method → gateway mapping (`PAYMENT_GATEWAY=stub` by default)
* `PaymentGatewayManager` — resolves configured gateways without controller `if/else` trees
* `StubPaymentGateway` — non-charging coming-soon / offline initialization (`supportsLiveCharging() === false`)
* Customer checkout, confirmation, and order pages show a professional **Payment Service Coming Soon** experience for online methods
* Placing an order or choosing “Pay” never marks `payment_status = paid`
* Payment remains `pending` until a genuine verified transition through `PaymentService` (admin/manual today)

No API credentials are required. The stub gateway never claims money was received. Future drivers (`MpesaGateway`, `StripeGateway`, etc.) will implement the same `PaymentGatewayInterface` without rewriting checkout.

## Payment Gateway Status

| Item | Value |
|------|--------|
| Current gateway | Stub / Offline (default) |
| Status | Coming Soon unless local PesaPal sandbox is explicitly enabled |
| Live / production payment | Not enabled |
| PesaPal | Sandbox adapter only (Phases 8A–8C) |
| Env default | `PAYMENT_GATEWAY=stub` |

**Current behavior:** Orders can be created without charging a payment method. Online methods show a clear Coming Soon experience unless local PesaPal sandbox credentials + enable flags are configured. `payment_status` stays `pending` until a genuine verified `PaymentService` transition.

**No production payment API is currently connected.**

### Future activation checklist

1. Implement the approved gateway adapter (`PaymentGatewayInterface`).
2. Configure environment variables (never commit secrets).
3. Configure the webhook endpoint.
4. Configure webhook signature verification.
5. Test sandbox payments.
6. Run security verification (IDOR, amount, idempotency, state machine).
7. Enable the gateway (`enabled` + `live_charging`).
8. Monitor transactions and audit history.
9. Only then enable production charging.

Fail closed: a misconfigured live gateway must fall back to stub/unavailable behavior and must never mark payment as paid.

## PesaPal Sandbox Integration

Phases 8A–8C add a **sandbox-only** PesaPal adapter and verification path.
**Production payment processing is NOT enabled.**

```text
Customer → Checkout → Order + PaymentTransaction
        → PaymentGatewayManager → PesapalGateway → PesapalClient
        → PesaPal SANDBOX (redirect_url)
        → IPN / browser callback
        → local tracking + merchant-reference binding
        → GetTransactionStatus (server-to-server)
        → status_code === 1 + amount + currency checks
        → PaymentService → payment_status = paid
```

### 1. Sandbox-only status

* Only `PESAPAL_ENV=sandbox` is permitted
* `PESAPAL_ENV=production` fail-closes (no production charging)
* Default storefront gateway remains `PAYMENT_GATEWAY=stub`
* Without credentials / enable flags, customers see **Coming Soon**

### 2. Required environment variables

```env
PAYMENT_GATEWAY=stub

PESAPAL_ENV=sandbox
PESAPAL_CONSUMER_KEY=
PESAPAL_CONSUMER_SECRET=
PESAPAL_IPN_ID=
PESAPAL_CALLBACK_URL=
PESAPAL_IPN_URL=
PESAPAL_TIMEOUT=15
```

Optional local enablement (never commit real secrets):

```env
PAYMENT_GATEWAY=pesapal
PAYMENT_PESAPAL_ENABLED=true
PAYMENT_PESAPAL_SANDBOX_CHARGING=true
PESAPAL_CALLBACK_URL="${APP_URL}/payments/pesapal/callback"
PESAPAL_IPN_URL="${APP_URL}/api/payments/pesapal/ipn"
```

### 3. Configure local sandbox credentials

1. Obtain sandbox consumer key/secret from PesaPal.
2. Put them only in local `.env` (not `.env.example`, git, tests, README, Blade, or logs).
3. Keep `PESAPAL_ENV=sandbox`.
4. Optionally set `PESAPAL_IPN_ID` if you already registered an IPN in the sandbox dashboard.
5. Expose callback/IPN URLs to the sandbox (local tunnel if needed).

### 4. Start the application

```bash
php artisan migrate
npm run build
php artisan serve
```

Health check (never prints secrets/tokens):

```bash
php artisan payments:pesapal-sandbox-check
php artisan payments:pesapal-sandbox-check --auth
```

### 5. Test checkout

1. Sign in as a customer, add a product, checkout with **PesaPal**.
2. Confirmation should show **Continue to PesaPal** (not Payment Successful).
3. Complete payment using PesaPal’s sandbox test mechanism only.
4. Return/IPN must still pass server-side verification before `paid`.

### 6. How IPN / callback work

| Endpoint | Route | Notes |
|----------|-------|--------|
| IPN | `POST/GET /api/payments/pesapal/ipn` | CSRF-exempt; never trusts payload status alone |
| Callback | `GET /payments/pesapal/callback` | Browser return is not proof of payment |

Both paths:

1. Load local payment by merchant reference
2. Require locally stored tracking ID binding
3. Call `GetTransactionStatus`
4. Require non-empty matching merchant reference
5. Require `status_code === 1`, amount, and currency match
6. Mutate state only through `PaymentService`

`payment_notification_receipts` provides replay/audit idempotency and is **not** payment authority.

### 7. Inspect payment status

* Customer: order confirmation / account order page payment panel
* Admin: admin order payment panel + payment status history
* DB: `orders.payment_status`, `payment_transactions`, `payment_status_histories`, `payment_notification_receipts`

### 8. Safely disable PesaPal

```env
PAYMENT_GATEWAY=stub
PAYMENT_PESAPAL_ENABLED=false
PAYMENT_PESAPAL_SANDBOX_CHARGING=false
```

Or remove credentials. Checkout returns to Coming Soon / stub behavior; no charge is attempted.

### 9. Remove sandbox credentials

Clear from local `.env`:

```env
PESAPAL_CONSUMER_KEY=
PESAPAL_CONSUMER_SECRET=
PESAPAL_IPN_ID=
```

Rotate any key that may have been exposed. Never commit replacements.

### 10. Production payments are NOT enabled

```text
NO PRODUCTION PAYMENT PROCESSING
NO REAL-MONEY CHARGING
NO PRODUCTION CREDENTIALS
SANDBOX ONLY
```

### Automated vs real sandbox tests

**Offline (default CI / `php artisan test`)** — HTTP fakes only:

```bash
php artisan test --filter=Pesapal
php artisan test
```

**Real sandbox (opt-in, credentials required)** — not part of the default suite:

```bash
PESAPAL_E2E=true php artisan test tests/Sandbox
```

If credentials are absent, real sandbox checks report:

```text
NOT EXECUTED — SANDBOX CREDENTIALS/PROVIDER TEST ENVIRONMENT UNAVAILABLE
```

Do not treat HTTP-fake tests as real sandbox E2E success.

### Security rules (Phase 8B preserved)

* Tracking ID strictly bound to local payment metadata
* Merchant reference strictly bound (`hash_equals`, empty rejected)
* Amount from `PaymentService::authoritativeAmount()`
* Currency from payment configuration
* `status_code === 1` is the only paid success condition
* Foreign/unbound FAILED notifications cannot terminalize orders
* Redirect hosts allow-listed (`cybqa.pesapal.com`)
* Duplicate IPNs idempotent
* Secrets never in Blade, JS, logs, DB records, tests, or git

## Phase 8C — PesaPal Sandbox Integration

See README section **PesaPal Sandbox Integration** above.

## Authorization & RBAC

See [docs/AUTHORIZATION.md](docs/AUTHORIZATION.md) for the enterprise RBAC guide:

* Permission naming (`resource.action`)
* Role → permission maps
* Ownership / IDOR rules
* Admin permission middleware
* Audit / security events
* How to protect new routes

```bash
php artisan db:seed --class=RbacSeeder
php artisan test --filter=RbacAuthorizationTest
```

## Technology Stack

* PHP 8.2+
* Laravel 12
* MySQL (intended production/local database)
* Blade templates
* JavaScript (Vite)
* CSS
* PHPUnit

## Architecture

| Area | Location |
|------|----------|
| HTTP routes | `routes/shop.php`, `routes/admin.php`, `routes/vendor.php` |
| Controllers | `app/Http/Controllers/`, `app/Http/Controllers/Vendor/` |
| Middleware | `admin`, `vendor`, `role`, marketplace preferences |
| Policies | `ProductPolicy`, `VendorPolicy`, `OrderItemPolicy` |
| Services | `OrderItemFulfillmentService`, `OrderFulfillmentSummary`, `PaymentService`, `CheckoutIdempotencyService`, `PaymentGatewayManager` |
| Payment gateways | `config/payments.php`, `PaymentGatewayInterface`, `StubPaymentGateway` (stub / coming soon only) |
| Notifications | Database channel (`app/Notifications/`) |
| Events / Listeners | `OrderItemStatusChanged`, `OrderPlaced` + listeners |
| Models | `app/Models/` |
| Marketplace helpers | `app/Support/Marketplace.php`, `app/helpers.php` |
| Views | `resources/views/` (including `vendor/`) |
| Migrations / seeders | `database/migrations/`, `database/seeders/` |
| Tests | `tests/Feature/`, `tests/Unit/` |

Cart and coupon state live in the session. Order placement recalculates line prices and stock from the database inside a transaction.

## Installation

```bash
git clone <repository-url>
cd ecommerce_platform
composer install
cp .env.example .env
php artisan key:generate
```

### Database (MySQL)

Create an empty MySQL database, then set credentials in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ecommerce_platform
DB_USERNAME=root
DB_PASSWORD=
```

Then run:

```bash
php artisan migrate
php artisan db:seed
npm install
npm run build
php artisan storage:link
php artisan serve
```

Visit `http://127.0.0.1:8000`.

### Demo accounts (local seeding only)

After `php artisan db:seed`:

| Email | Password | Role | Notes |
|-------|----------|------|-------|
| `admin@example.com` | `password` | admin | Full `/admin` console |
| `test@example.com` | `password` | customer | Storefront buyer |
| `seller@example.com` | `password` | vendor | Linked to Tech Haven store |
| `fashion@example.com` | `password` | vendor | Linked to Fashion Plus store |

Do not use these credentials in production.

## Testing

Tests use SQLite in-memory (`phpunit.xml`) and do not require MySQL.

```bash
php artisan test
```

## Security

Implemented protections include:

* Admin routes gated by `auth` + `admin` middleware
* Vendor routes gated by `auth` + `vendor` middleware (requires linked store)
* Product mutations authorized by policy (admin global, vendor ownership)
* Vendor forms cannot set or change `vendor_id`
* Checkout totals calculated from live product rows (session prices ignored)
* Stock locked with `lockForUpdate()` during order placement
* Order confirmation / account / vendor order views enforce ownership isolation
* Vendor fulfillment updates authorized by `OrderItemPolicy` + state machine (cross-vendor IDOR blocked)
* Admin fulfillment overrides require admin middleware + policy + explicit transition/reason rules
* Notification mark-as-read scoped to authenticated notifiable ownership
* Registration always creates `customer` role
* CSRF protection on web forms
* Throttling on login, register, checkout, contact, reviews, and questions
* Upload MIME allow-lists for product/avatar images
* XSS hardening in search/recently-viewed JavaScript (DOM APIs / URL checks)

Payment charging is **not** integrated — selected methods are recorded, customers see a coming-soon experience for online methods, and orders remain `pending` until a real verified gateway (or admin/manual `PaymentService` transition) is used.

## Project Structure

```text
app/Http/Controllers         Storefront, account, checkout, admin
app/Http/Controllers/Vendor  Vendor dashboard, products, orders, profile
app/Http/Middleware          Admin, vendor, role, marketplace preferences
app/Models                   Eloquent models (User ↔ Vendor ↔ Product)
app/Policies                 ProductPolicy, VendorPolicy, OrderItemPolicy
app/Services                 OrderItemFulfillmentService
database/migrations          Schema (MySQL-oriented)
database/seeders             Demo users + marketplace catalog
resources/views/vendor       Seller hub UI
tests/Feature                Security and functional regression tests
```

## Future Improvements

1. Real payment gateway integration (M-Pesa / card)
2. Admin create/update UI for categories, coupons, and inventory adjustments
3. Email notifications and password reset flow
4. Persistent cart for authenticated users
5. Stronger content moderation for reviews/questions
6. Vendor payout / commission reporting
7. Docker Compose for one-command local MySQL + app setup

## License

This project is open-sourced under the MIT license.
