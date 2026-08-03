# Authorization & RBAC Developer Guide

SANA Market uses a layered authorization model:

1. **Authentication** — Laravel session identity
2. **Permission check** — RBAC `resource.action` permissions
3. **Ownership / object check** — vendor/customer resource isolation
4. **Audit** — append-only business + security events

Frontend `@canPermission` / hidden nav items are **UX only**. The backend is the security boundary.

## Core components

| Piece | Location |
|-------|----------|
| Permission catalog + role maps | `config/authorization.php` |
| Resolver | `App\Services\Authorization\PermissionResolver` |
| Role assignment | `App\Services\Authorization\RoleAssignmentService` |
| Audit writer | `App\Services\Authorization\AuditLogger` |
| Middleware | `admin`, `vendor`, `permission:…` |
| Policies | `app/Policies/*` |
| Seeder | `Database\Seeders\RbacSeeder` |

## Roles

Platform: `super_admin`, `admin`, `product_manager`, `inventory_manager`, `order_manager`, `customer_support`, `vendor_manager`, `marketing_manager`, `review_moderator`, `finance_manager`, `auditor`

Marketplace: `vendor`, `customer`

Legacy `users.role` (`admin|vendor|customer`) remains for marketplace identity and is bridged to RBAC when a user has no `user_roles` rows yet.

## Permission naming

Use `resource.action` (examples: `products.update`, `orders.manage_any`, `reviews.moderate`).

`products.manage_any` / `orders.manage_any` grant cross-tenant platform access. Vendors get capability permissions **without** `manage_any` and must pass ownership checks.

## Protecting a new admin route

```php
Route::get('/admin/reports', ...)->middleware(['auth', 'admin', 'permission:audit_logs.view']);
```

Optionally hide the nav link:

```blade
@canPermission('audit_logs.view')
  <a href="...">Reports</a>
@endcanPermission
```

## Protecting a resource action

```php
$this->authorize('update', $product); // ProductPolicy: permission + ownership
```

## Creating a permission

1. Add the name to `config/authorization.php` → `permissions`
2. Add it to the appropriate role maps
3. Run `php artisan db:seed --class=RbacSeeder`
4. Enforce with `permission:` middleware and/or policy

## Creating a role

1. Add a map under `config/authorization.php` → `roles`
2. Reseed `RbacSeeder`
3. Assign via admin Users UI or `RoleAssignmentService`

## Ownership rules

| Resource | Rule |
|----------|------|
| Product (vendor) | `product.vendor_id === auth.user.vendor.id` |
| Order (customer) | `order.user_id === auth.id` |
| Order (vendor) | vendor has a line item on the order |
| Address | `address.user_id === auth.id` |
| Payment manage | `payments.manage` only (never vendors) |

## Fail closed

Missing role/permission, inactive user, unresolved owner, or resolver failure → **deny** (403 authenticated / 401 guest).

## Super Admin protections

- Protected role; ordinary admins cannot assign `super_admin`
- Cannot remove the last Super Admin
- Role changes are security-audited

## Audit & security events

- `audit_logs` — business + security category trail (append-only)
- `security_events` — login failures, permission denials, escalation attempts

Never log passwords, tokens, or secrets.

## Tests

```bash
php artisan test --filter=RbacAuthorizationTest
php artisan test
```
