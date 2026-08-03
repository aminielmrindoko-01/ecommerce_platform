# Phase 2 Security Hardening Report

**Branch:** `cursor/rbac-security-hardening-8365`  
**Base:** `main` (Enterprise RBAC #9)  
**Constraint:** Existing RBAC preserved; no duplicate authorization system.

---

## Architecture

Deny-by-default stack:

Authenticated user → active account → RBAC roles → permissions → resource ownership → allow/deny

Components: `config/authorization.php`, `PermissionResolver`, `RoleAssignmentService`, policies, `admin` / `vendor` / `permission` / `role` / `stepup` middleware, `AuditLogger`, MFA + step-up services.

Frontend permission checks are UX only.

---

## Roles

`super_admin`, `admin`, `product_manager`, `inventory_manager`, `order_manager`, `customer_support`, `vendor_manager`, `marketing_manager`, `review_moderator`, `finance_manager`, `auditor`, `vendor`, `customer`

## Permissions

Full catalog in `config/authorization.php` (`admin.access`, product/inventory/order/vendor/coupon/review/user/role/finance/audit/settings, plus customer/vendor self-service).

## Ownership

- Vendor products / inventory / vendor settings: vendor ownership or `*.manage_any`
- Customer orders / addresses / wishlist / profile: `user_id === auth.id`
- Payments: `payments.manage` (admin/finance), never client identity fields

## MFA

**Implemented and tested (TOTP):**

- Enrollment, challenge after login, recovery codes (hashed), disable with password step-up + TOTP
- Secrets encrypted at rest; never returned after enrollment confirm
- Config roles: `super_admin`, `admin`, `finance_manager`, `vendor_manager`
- `MFA_ENFORCE_ENROLLMENT` defaults **false** (opt-in for production)

**Not implemented:** WebAuthn, SMS, long-lived trusted-device cookies.

## Step-up authentication

**Implemented:**

- `StepUpAuthService` + `RequireStepUpMiddleware` (`stepup`)
- Password confirmation session TTL (`STEP_UP_TTL_SECONDS`, default 300)
- Applied to `PUT /admin/users/{id}`; MFA disable uses the same confirmation path
- Audited / security-evented on failure

**Extend as needed** for payment config, payouts, Super Admin creation routes when those UIs land.

## Legacy role

`users.role` retained as marketplace identity.

- **Cannot** widen RBAC (proven: `role=admin` + RBAC `customer` → no `admin.access`)
- Materializes mapped RBAC role only when `user_roles` empty
- `isAdmin()` / `RoleMiddleware` / payment FormRequests use RBAC permissions only
- Removal plan documented in `docs/AUTHORIZATION.md`

## Audit

Append-only audit logs + security events. No admin mutate/delete routes for audit history.

Covered in code paths: login, permission denied, role changes, MFA enable/disable, step-up, vendor toggle, review moderate, product/order mutations where wired.

## Security tests

| Suite | Result |
|-------|--------|
| `RbacAuthorizationTest` | PASS |
| `SecurityHardeningTest` (14 cases: escalation, ownership, mass assignment, MFA, step-up, cache, fail-closed, UI bypass) | PASS |
| Full `php artisan test` | PASS (target ≥208 / 755) |
| `npm run build` | PASS |

## Vulnerabilities found & fixed

1. **Legacy `users.role` could act as auth authority** → contained; RBAC-only for admin/super-admin checks  
2. **RoleMiddleware used legacy column** → RBAC role names  
3. **Privileged accounts lacked MFA** → TOTP MFA added  
4. **Sensitive role changes lacked step-up** → middleware + tests  
5. **Payment/fulfillment FormRequests used `isAdmin()`** → permission-based

## Remaining risks

1. `MFA_ENFORCE_ENROLLMENT` off by default — enable in production  
2. `users.role` column still present (intentional bridge)  
3. Some admin modules still read-oriented / incomplete  
4. Step-up not yet on every future finance/payout route (apply when those routes ship)  
5. No WebAuthn / hardware keys  
6. Permission cache TTL 5 minutes with explicit invalidation on role sync — monitor for edge cases

## Files changed (this hardening PR)

- `.env.example`
- `app/Http/Controllers/AuthController.php`
- `app/Http/Controllers/Security/MfaController.php`
- `app/Http/Controllers/Security/StepUpController.php`
- `app/Http/Middleware/AdminMiddleware.php`
- `app/Http/Middleware/RequireStepUpMiddleware.php`
- `app/Http/Middleware/RoleMiddleware.php`
- `app/Http/Requests/Admin/UpdateOrderItemFulfillmentRequest.php`
- `app/Http/Requests/Admin/UpdateOrderPaymentRequest.php`
- `app/Models/User.php`
- `app/Services/Authorization/PermissionResolver.php`
- `app/Services/Authorization/RoleAssignmentService.php`
- `app/Services/Security/MfaService.php`
- `app/Services/Security/StepUpAuthService.php`
- `app/Services/Security/TotpService.php`
- `bootstrap/app.php`
- `config/authorization.php`
- `database/migrations/2026_08_03_120000_add_mfa_columns_to_users_table.php`
- `docs/AUTHORIZATION.md`
- `resources/views/account/security.blade.php`
- `resources/views/security/mfa-challenge.blade.php`
- `resources/views/security/mfa-enroll.blade.php`
- `resources/views/security/step-up.blade.php`
- `routes/admin.php`
- `routes/shop.php`
- `tests/Feature/AdminAccessTest.php`
- `tests/Feature/SecurityHardeningTest.php`
- `docs/SECURITY_HARDENING_REPORT.md` (this file)

## Database changes

- `2026_08_03_120000_add_mfa_columns_to_users_table.php`  
  Columns: `mfa_enabled`, `mfa_secret` (encrypted), `mfa_confirmed_at`, `mfa_recovery_codes` (encrypted hashed list)

(Prior RBAC tables from #9 unchanged.)

## Production checklist

- [ ] Run migrations (`php artisan migrate`)
- [ ] Ensure `RbacSeeder` / permission sync has run
- [ ] `APP_KEY` set (required for encrypted MFA secrets)
- [ ] Set `MFA_ENFORCE_ENROLLMENT=true` for privileged roles
- [ ] Enroll Super Admin MFA before go-live
- [ ] Set `STEP_UP_TTL_SECONDS` appropriately (default 300)
- [ ] Confirm `MFA_ISSUER` branding
- [ ] Verify audit log storage retention / access limited to auditors
- [ ] Do **not** expose MFA secrets in logs or support tooling dumps
