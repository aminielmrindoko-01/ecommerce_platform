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
| Policies | `ProductPolicy`, `VendorPolicy` |
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
app/Policies                 ProductPolicy, VendorPolicy
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
