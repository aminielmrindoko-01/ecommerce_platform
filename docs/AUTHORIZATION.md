# Authorization & RBAC Developer Guide (Phase 2 Hardened)

SANA Market authorization is layered and **deny-by-default**:

1. Authentication (Laravel session)
2. Active account check
3. RBAC roles → permissions
4. Resource ownership
5. Allow / Deny

Frontend `@canPermission` / hidden nav = **UX only**. Backend is the security boundary.

---

## 1. Authentication architecture

- Session cookies (Laravel defaults: HttpOnly, encrypted session)
- Login throttled; inactive accounts rejected
- Privileged users with MFA enabled complete a TOTP challenge before the session is trusted
- Registration always creates `customer` identity + RBAC `customer` role (client cannot set role)
- Password step-up confirms sensitive mutations within a short TTL

## 2. RBAC architecture

| Piece | Location |
|-------|----------|
| Catalog + role maps | `config/authorization.php` |
| Resolver | `App\Services\Authorization\PermissionResolver` |
| Role assignment | `App\Services\Authorization\RoleAssignmentService` |
| Audit | `App\Services\Authorization\AuditLogger` |
| MFA | `App\Services\Security\MfaService` + `TotpService` |
| Step-up | `App\Services\Security\StepUpAuthService` |
| Middleware | `admin`, `vendor`, `permission:…`, `role:…`, `stepup` |
| Policies | `app/Policies/*` |

Do **not** introduce a second authorization system. Extend this stack.

## 3. Permission naming

`resource.action` — e.g. `products.update`, `orders.manage_any`, `reviews.moderate`.

`*.manage_any` grants cross-tenant platform access. Vendors get capabilities **without** `manage_any` and must pass ownership.

## 4. Role hierarchy (logical)

| Role | Intent |
|------|--------|
| `super_admin` | All permissions (`*`); protected assignment |
| `admin` | Broad operations (not unrestricted security assignment) |
| `product_manager` | Catalog + publish |
| `inventory_manager` | Stock adjust / history |
| `order_manager` | Order lifecycle |
| `customer_support` | Customer + order view |
| `vendor_manager` | Vendor lifecycle |
| `marketing_manager` | Coupons |
| `review_moderator` | Review moderation |
| `finance_manager` | Payments / refunds / payouts |
| `auditor` | Read-only audit / security / finance views |
| `vendor` | Own catalog / inventory / orders |
| `customer` | Own orders / wishlist / addresses / profile |

## 5. Ownership rules

| Resource | Rule |
|----------|------|
| Product (vendor) | `product.vendor_id === auth.vendor.id` |
| Order (customer) | `order.user_id === auth.id` |
| Order (vendor) | vendor has a line item on the order |
| OrderItem fulfillment | vendor owns the item **or** `orders.manage_any` |
| Address / wishlist | `user_id === auth.id` |
| Vendor profile | owns vendor record **or** `vendors.*` platform perms |
| Payments manage | `payments.manage` only (never client-supplied ids) |

## 6. Admin authorization

- Shell: `auth` + `admin` (`admin.access`) + active account
- Module routes add `permission:…` for view/mutate
- Mutations that change roles require `stepup`
- `MFA_ENFORCE_ENROLLMENT` can require TOTP enrollment for privileged roles before admin shell
- Nav is permission-aware (UX); HTTP still enforces permissions

### Admin route matrix (summary)

| Route | Methods | Auth | Permission | Sensitive / step-up | Audit |
|-------|---------|------|------------|---------------------|-------|
| `/admin` | GET | admin | `admin.access` / `dashboard.view` | — | — |
| `/admin/products` | GET | admin | `products.view` | — | — |
| `/admin/products/{id}` | DELETE | admin | `products.delete` | — | product delete |
| `/admin/orders` | GET | admin | `orders.view` | — | — |
| `/admin/orders/{id}` | PUT | admin | `orders.update` | — | order status |
| `/admin/orders/{order}/payment` | PATCH | admin | `payments.manage` | finance | payment change |
| `/admin/payments` | GET | admin | `payments.view` / `transactions.view` | — | — |
| `/admin/payments/{payment}/refund` | POST | admin | `refunds.create` | **stepup** | `REFUND_*` |
| `/admin/payments/reconciliations` | GET | admin | `payments.view` | — | — |
| `/account/payments` | GET | auth | own payments | — | — |
| `/admin/finance/ledger` | GET | admin | `ledger.view` | — | — |
| `/admin/finance/payouts` | GET | admin | `payouts.view` | — | — |
| `/admin/finance/payouts/{id}/approve` | POST | admin | `payouts.approve` | **stepup** | `PAYOUT_APPROVED` |
| `/admin/finance/payouts/{id}/process` | POST | admin | `payouts.process` | **stepup** | `PAYOUT_COMPLETED` |
| `/vendor/finance` | GET | vendor | own finance | — | — |
| `/admin/operations/returns` | GET | admin | `returns.view` | — | — |
| `/admin/operations/returns/{id}/refund` | POST | admin | `refunds.create` | **stepup** | `RETURN_REFUNDED` |
| `/admin/operations/disputes` | GET | admin | `disputes.view` | — | — |
| `/admin/operations/chargebacks` | POST | admin | `chargebacks.create` | **stepup** | `CHARGEBACK_RECEIVED` |
| `/admin/operations/holds/{id}/release` | POST | admin | `settlement_holds.manage` | **stepup** | `SETTLEMENT_HOLD_RELEASED` |
| `/admin/operations/commission` | POST | admin | `commission.manage` | **stepup** | `COMMISSION_CONFIG_UPDATED` |
| `/account/returns` | GET/POST | auth | own returns | — | `RETURN_*` |
| `/account/disputes` | GET/POST | auth | own disputes | — | `DISPUTE_*` |
| `/vendor/returns` | GET/POST | vendor | own store returns | — | `RETURN_*` |
| `/vendor/disputes` | GET/POST | vendor | own store disputes | — | — |
| `/admin/orders/…/fulfillment` | PATCH | admin | `orders.update` | — | fulfillment |
| `/admin/users` | GET | admin | `users.view` | — | — |
| `/admin/users/{id}` | PUT | admin | `users.update` | **stepup** | `USER_ROLE_CHANGED` |
| `/admin/vendors` | GET | admin | `vendors.view` | — | — |
| `/admin/vendors/{vendor}/status` | POST | admin | approve/reject/suspend/update | — | vendor lifecycle |
| `/admin/vendors/{id}/toggle` | POST | admin | `vendors.suspend` / approve | — | vendor suspend/approve |
| `/vendor/apply` | GET/POST | auth | customer apply | throttle | `VENDOR_APPLICATION_SUBMITTED` |
| `/account/orders/{order}/cancel` | POST | auth | own order + cancel rules | — | `ORDER_CANCELLED` |
| `/admin/reviews` | GET | admin | `reviews.view` | — | — |
| `/admin/reviews/{review}/moderate` | PATCH | admin | `reviews.moderate` (+ action) | — | review approve/reject |
| `/admin/roles` | GET | admin | `roles.view` | — | — |
| `/admin/inventory` | GET | admin | `inventory.view` | — | — |
| `/admin/audit-logs` | GET | admin | `audit_logs.view` | read-only | — |
| `/admin/security-events` | GET | admin | `security_events.view` | read-only | — |

## 7. Vendor isolation

- `VendorMiddleware`: marketplace `users.role === vendor` **and** `vendor.access` **and** linked store **and** `vendor.canSell()` (approved lifecycle)
- Vendor lifecycle transitions use `VendorLifecycleService` with `vendors.approve` / `reject` / `suspend` / `update`
- Policies deny cross-vendor product/order/inventory access
- Order item ownership prefers `order_items.vendor_id` snapshot
- Never trust client `vendor_id` for ownership

See also `docs/ORDER_ARCHITECTURE.md`.

## 8. Customer isolation

- Orders, addresses, wishlist scoped to `auth.id`
- Policies deny IDOR/BOLA across customers
- Never trust client `user_id` / `customer_id`
- Customer cancel uses `OrderPolicy::cancel` + `OrderService` (pending/confirmed only)

## 9. MFA

- TOTP (RFC 6238); secrets encrypted at rest (`encrypted` cast)
- Recovery codes stored hashed; plaintext shown once at enrollment
- Required roles (config): `super_admin`, `admin`, `finance_manager`, `vendor_manager`
- `MFA_ENFORCE_ENROLLMENT=true` blocks admin shell until enrolled
- Disable MFA requires password step-up + valid TOTP
- Never log TOTP secrets / recovery plaintext / passwords / tokens
- Not implemented: WebAuthn, SMS OTP, long-lived trusted-device cookies

Routes: `/account/security`, `/security/mfa/*`, challenge after login when enabled.

## 10. Step-up authentication

Flow:

```
Authenticated session → sensitive action → permission check
→ require recent password confirmation (TTL) → perform → audit
```

- Session key `auth.step_up_confirmed_at`
- TTL: `STEP_UP_TTL_SECONDS` (default 300)
- Middleware alias: `stepup`
- Applied to admin user role changes; MFA disable uses the same service
- Failed / missing step-up creates security events

Configurable sensitive actions (extend middleware on routes): Super Admin creation, privileged role changes, payment config, large refunds/payouts, MFA disable, security settings.

## 11. Audit logging

Append-only `audit_logs`. Ordinary admins cannot mutate history (no update/delete routes).

Events include: `LOGIN_SUCCESS`, `LOGIN_FAILED`, `PERMISSION_DENIED`, `USER_ROLE_CHANGED`, `PRODUCT_*`, `ORDER_CREATED`, `ORDER_CONFIRMED`, `ORDER_PROCESSING`, `ORDER_SHIPPED`, `ORDER_DELIVERED`, `ORDER_CANCELLED`, `VENDOR_*`, `REVIEW_*`, `MFA_*`, `STEP_UP_*`, security setting changes.

Payloads should reconstruct actor, target, before/after where applicable — **never** secrets.

## 12. Security events

Suspicious patterns write `security_events`, e.g.:

- Repeated auth / authorization failures
- Cross-tenant access attempts
- Privilege escalation attempts
- Unauthorized role / permission modification
- MFA / step-up manipulation

Do not log passwords, TOTP secrets, tokens, or full payment credentials.

## 13. Legacy `users.role` migration

`users.role` is **marketplace identity** (`customer|vendor|admin`), not authorization authority.

| Usage | Classification | Status |
|-------|----------------|--------|
| VendorMiddleware `role === vendor` | Marketplace identity + permission | Kept (with `vendor.access`) |
| PermissionResolver materialize | Compatibility | Maps empty `user_roles` → RBAC once |
| Admin users UI display | Display | Kept |
| `isAdmin()` | Authorization | **RBAC `admin.access` only** |
| `isSuperAdmin()` | Authorization | **RBAC `super_admin` only** |
| `RoleMiddleware` | Authorization | **RBAC role names only** |
| FormRequests (payment/fulfillment) | Authorization | **permissions only** |

**Priority:** Assigned RBAC roles always win. `users.role` cannot widen permissions (e.g. `role=admin` + RBAC `customer` ≠ admin access).

**Removal plan:**

1. Ensure every user has `user_roles` (materialize / backfill)
2. Replace marketplace checks with `hasRole('vendor')` / account-type column
3. Stop materializing from `users.role`
4. Rename to `account_type` for shop UX
5. Drop column after one release with monitoring

## 14. Testing methodology

- Feature tests hit real HTTP routes (not only unit mocks)
- Cover privilege escalation, IDOR/BOLA, mass assignment, fail-closed, cache invalidation, MFA, step-up
- Frontend hide/show is **not** security; always re-test with direct HTTP

```bash
php artisan test --filter=RbacAuthorizationTest
php artisan test --filter=SecurityHardeningTest
php artisan test
npm run build
```

## 15. Adding a new permission

1. Add slug to `config/authorization.php` → `permissions`
2. Attach to role maps under `roles`
3. Run / update `RbacSeeder` (or sync command used in deploy)
4. Protect route: `middleware('permission:resource.action')` and/or policy
5. Audit sensitive mutations
6. Add feature tests (allow + deny)

## 16. Adding a new role

1. Add role map in `config/authorization.php`
2. Seed role + permissions
3. Decide MFA requirement (`mfa.required_roles`)
4. Document least-privilege intent
5. Tests for allow/deny and non-escalation

## 17. Protecting a new route

```php
Route::post('/admin/x', [...])
    ->middleware(['auth', 'admin', 'permission:products.create', 'stepup']);
```

Prefer specific permissions over bare `admin` middleware for mutations.

## 18. Protecting a new resource

```php
$this->authorize('update', $product); // policy: permission + ownership
```

Never authorize from request payload identity fields.

## 19. Security checklist for developers

- [ ] Never trust client `user_id` / `vendor_id` / `role` / `permissions`
- [ ] Prefer `permission:` over legacy role checks
- [ ] Add ownership checks for tenant resources
- [ ] Audit sensitive mutations
- [ ] Require `stepup` for privilege / finance / MFA-disable actions
- [ ] Do not log secrets
- [ ] Fail closed on missing roles/permissions/inactive users
- [ ] Invalidate permission cache on role/permission changes
- [ ] Add HTTP feature tests for allow + deny paths

---

## 20. Definition of Done — Security

Every new protected SANA Market feature **must** ship with all of the following before merge:

1. **Authentication** — route behind `auth` (or documented public exception)
2. **Permission** — granular `permission:…` / policy check (not bare `admin.access` alone for mutations)
3. **Ownership / resource authorization** — tenant isolation for vendor/customer resources; never trust client identity fields
4. **Input validation** — FormRequest / allowlisted fields; sensitive columns not mass-assignable
5. **Business authorization** — state-machine / domain rules after ACL
6. **Audit logging** — sensitive mutations emit audit (and security events on abuse)
7. **Security tests** — feature/HTTP tests covering allow **and** deny (including IDOR/BOLA and privilege escalation where relevant)
8. **Transaction safety** — multi-row mutations use DB transactions / row locks where concurrency matters
9. **Secure error handling** — no secrets, SQL, or filesystem paths in client responses

Frontend permission checks are **UX only**. Backend denial is mandatory even when UI hides the action.

Step-up (`stepup`) is required for: privileged role changes, MFA disable, and future payment-config / payout / Super-Admin creation routes when those modules exist.

### Catalog operations note

Product/category/inventory admin modules use the services in `App\Services\Catalog\*` and existing RBAC permissions. Stock mutations go through `InventoryService` (locked + audited). See `docs/CATALOG_OPERATIONS.md`.

### Marketplace / orders note (Phase 4)

Multi-vendor orders, vendor lifecycle, order state machine, and cart price security are documented in `docs/ORDER_ARCHITECTURE.md`. Checkout uses `OrderService` + reserve-at-place; payment remains `PaymentService` / `PaymentGatewayInterface`.

### Payments note (Phase 5)

Payment attempts, inventory settlement on verified payment, refunds (step-up), reconciliation flags, and webhook replay protections are documented in `docs/PAYMENT_ARCHITECTURE.md`.

**PAYMENT INTEGRATION STATUS: SANDBOX / NOT PRODUCTION-READY** (stub default; PesaPal sandbox optional).

### Finance / ledger note (Phase 6)

Double-entry ledger, vendor entitlements, commission snapshots, payable derivation, and sandbox payouts are documented in `docs/FINANCE_ARCHITECTURE.md`.

**PAYOUT INTEGRATION STATUS: SANDBOX / NOT PRODUCTION-READY**

---

## Least-privilege notes (recommendations only)

Do **not** change these without product sign-off:

- `admin` includes `payments.manage` and `refunds.create` — consider finance-only for refunds/payouts
- `product_manager` lacks `products.delete` (good) — confirm intentional
- `order_manager` has `orders.cancel` but not `orders.refund` (good)
- `auditor` is read-oriented — keep write permissions out
