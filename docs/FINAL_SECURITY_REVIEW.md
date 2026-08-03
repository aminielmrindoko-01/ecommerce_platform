# Final Security Review — PR #10

**Date:** 2026-08-03  
**Branch:** `cursor/rbac-security-hardening-8365`  
**Method:** Source inspection + runtime HTTP feature tests (not documentation-only claims)

---

## PASS

| Control | Evidence |
|---------|----------|
| RBAC priority (active → roles → permissions → ownership → deny) | `PermissionResolver`; legacy `role=admin` + RBAC `customer` denied in tests |
| Legacy `users.role` cannot widen RBAC | `isAdmin()` / `RoleMiddleware` / FormRequests permission-based |
| Admin routes use granular permissions beyond `admin.access` | `routes/admin.php` matrices |
| Vendor / customer object-level auth (IDOR) | Policies + HTTP deny tests (PUT/PATCH/DELETE included) |
| MFA secrets encrypted + hidden | User casts `encrypted`; `$hidden` includes `mfa_secret` / recovery |
| MFA recovery single-use | `MfaService::verifyLogin` + test |
| MFA challenge required before trusted session | Login logs out then pending session; guest until challenge |
| `MFA_ENFORCE_ENROLLMENT=true` blocks admin shell | `AdminMiddleware` + test |
| Step-up TTL expiry enforced | Expired session timestamp redirects to step-up |
| Step-up not forgeable via body/query | Server session only; client `intended` ignored |
| Mass assignment of role / MFA / is_verified / address `user_id` | Fillable allowlists + HTTP tests |
| Audit / security event HTTP immutability | No mutate routes; model blocks update/delete |
| Fail-closed inactive / missing roles | Tests deny |
| Permission cache invalidation on role sync | `RoleAssignmentService` + `forget()` |
| Frontend hide ≠ security | Direct HTTP still 403 |

---

## FAIL (found in review — fixed this pass)

| Issue | Fix |
|-------|-----|
| **Open redirect after step-up** — client `intended` accepted | Ignore client target; session stores relative `RequestUri` only; sanitize path |
| **Vendor toggle OR permission** — approve-only could suspend (and vice versa) | Directional permission check in `toggleVendorVerification` |
| **MFA secrets not in audit scrub list** | Scrub `mfa_secret`, recovery codes, etc. |
| **`LOGIN_SUCCESS` missing after MFA challenge** | Logged after successful challenge |
| **Address `user_id` / Vendor `is_verified` mass-assignable** | Removed from fillable; server-side assignment only |
| **Audit/security rows mutable via Eloquent** | Model `updating`/`deleting` guards |

---

## WARNING

| Item | Notes |
|------|-------|
| `MFA_ENFORCE_ENROLLMENT` defaults **false** | Must enable for production privileged roles |
| `SESSION_ENCRYPT=false` / `SESSION_SECURE_COOKIE` unset | Set secure cookies + HTTPS in production |
| Payment status mutation lacks `stepup` | Acceptable until finance module expands; add when payout/refund UIs ship |
| Platform `admin` can assign non-protected roles (e.g. finance_manager) | Intentional breadth; tighten only with product sign-off |
| Audit events `PRODUCT_CREATED`, `INVENTORY_ADJUSTED`, `REFUND_CREATED`, `PAYOUT_PROCESSED`, `PERMISSION_CHANGED`, `SECURITY_SETTING_CHANGED` | Not emitted — modules incomplete or not wired; not fake-logged |
| MFA enrollment optional for non-admin surfaces when enforce off | Expected |
| Permission cache TTL 5 minutes | Invalidated on role sync; residual TTL risk if DB edited out-of-band |
| `Order.user_id` remains fillable | Checkout sets server-side; no customer HTTP update found — watch future APIs |

---

## LEGACY

| Item | Classification |
|------|----------------|
| `users.role` column | Marketplace identity (`customer\|vendor\|admin`) |
| `VendorMiddleware` requires `role === vendor` **and** `vendor.access` | Identity + permission |
| `User::isVendor()` uses `users.role` | Identity helper, not permission grant |
| `legacy_role_map` materialize when `user_roles` empty | Compatibility only; cannot widen after RBAC assigned |
| Removal plan | Documented in `docs/AUTHORIZATION.md` — do not drop column yet |

### Legacy usage classification (security-sensitive vs not)

| Pattern | Classification |
|---------|----------------|
| `isAdmin()` → `admin.access` | Authz via RBAC (OK) |
| `isSuperAdmin()` → RBAC role name | Authz via RBAC (OK) |
| `RoleMiddleware` → `roleNames()` | Authz via RBAC (OK) |
| `VendorMiddleware` `role !== vendor` | Marketplace identity gate (legacy, paired with permission) |
| `PermissionResolver` materialize | Compatibility |
| Admin users UI display of `role` | Display |
| `RoleAssignmentService` syncing legacy column | Data compatibility |

**No remaining path found where `users.role` alone grants admin/finance permissions when RBAC denies them.**

---

## PRODUCTION REQUIRED

1. `php artisan migrate` (incl. MFA columns)
2. RBAC seeded / synced (`RbacSeeder`)
3. Valid `APP_KEY` (encrypts MFA secrets)
4. `MFA_ENFORCE_ENROLLMENT=true`
5. Enroll Super Admin (and other privileged roles) before go-live
6. `SESSION_SECURE_COOKIE=true`, serve HTTPS, prefer `SESSION_ENCRYPT=true`
7. `APP_DEBUG=false`, strong `APP_KEY`, production `LOG_LEVEL`
8. Confirm login throttle remains enabled
9. Restrict DB access so audit tables cannot be truncated by app roles

---

## FUTURE (not required for initial production)

- WebAuthn / hardware keys
- Long-lived trusted-device cookies
- Step-up on payout / refund / payment-config routes (when built)
- Drop / rename `users.role` after full backfill
- Wire missing audit actions for unfinished modules
- Least-privilege split of `admin` finance permissions (product decision)

### Sensitive actions currently protected by step-up

- `PUT /admin/users/{id}` (role assignment)
- MFA disable (password confirm via `StepUpAuthService`)

### Sensitive actions not yet route-protected (modules absent / incomplete)

- Creating Super Admin via dedicated UI beyond role sync (covered partially by protected role + step-up on user update)
- Payment gateway configuration changes
- Large refunds / payout processing
- Security settings management UI

---

## TEST RESULTS

| Metric | Value |
|--------|-------|
| Total tests | **222 passed** |
| Total assertions | **790** |
| New review tests | `FinalSecurityReviewTest` (14 cases) |
| Failed tests | **0** |
| Build | **PASS** (`npm run build`) |

---

## FILES CHANGED (this review pass only)

- `app/Http/Controllers/Security/StepUpController.php`
- `app/Http/Controllers/Security/MfaController.php`
- `app/Http/Controllers/AdminController.php`
- `app/Http/Controllers/AccountController.php`
- `app/Http/Controllers/CheckoutController.php`
- `app/Http/Middleware/RequireStepUpMiddleware.php`
- `app/Services/Authorization/AuditLogger.php`
- `app/Models/AuditLog.php`
- `app/Models/SecurityEvent.php`
- `app/Models/Vendor.php`
- `app/Models/Address.php`
- `resources/views/security/step-up.blade.php`
- `database/seeders/MarketplaceSeeder.php`
- `tests/Support/CreatesMarketplace.php`
- `tests/Feature/FinalSecurityReviewTest.php`
- `tests/Feature/SecurityHardeningTest.php`
- `tests/Feature/RbacAuthorizationTest.php`
- `docs/AUTHORIZATION.md`
- `docs/FINAL_SECURITY_REVIEW.md` (this file)
