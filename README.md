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

Payment charging is **not** live. Phase 5 adds a secure operations layer for recording and administering payment state:

* `orders.payment_status` — dedicated payment lifecycle (`pending`, `processing`, `paid`, `failed`, `cancelled`, `refunded`, `partially_refunded`)
* `orders.status` — unchanged legacy admin/order lifecycle (`pending`, `paid`, `shipped`, `completed`)
* `payment_transactions` — order-level records (`manual` / `stub` providers only) with unique `reference` and unique `provider_reference`
* `PaymentService` — central state machine with `lockForUpdate()`, amount/currency checks against `order.total_price`, idempotent provider references, and audit history
* Admin payment updates via `PATCH /admin/orders/{order}/payment` (auth + admin + policy + FormRequest)
* Customer-visible payment status/reference on own orders only
* Checkout initializes `payment_status=pending` and a stub pending transaction; checkout form uses a one-time idempotency token
* Database notifications for payment successful / failed / cancelled

Vendors cannot mutate payments. Live M-Pesa/card/PayPal charging, payouts, commissions, wallets, and full refund workflows remain out of scope.

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
| Services | `OrderItemFulfillmentService`, `OrderFulfillmentSummary` |
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

Payment charging is **not** integrated — selected methods are recorded and orders remain `pending` until a real gateway is added.

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
