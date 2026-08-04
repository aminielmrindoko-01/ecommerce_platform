# Super Admin Bootstrap

Secure **first Super Admin** creation for SANA Market.

This solves only:

```
NO SUPER ADMIN → BOOTSTRAP COMMAND → SUPER ADMIN CREATED → BOOTSTRAP LOCKED → NORMAL RBAC
```

**No HTTP endpoint. No hard-coded credentials. No backdoor.**

---

## Initial setup

1. Migrate and ensure RBAC catalog (command auto-seeds roles/permissions if missing):

```bash
php artisan migrate
php artisan admin:create-super-admin
```

2. Enter **Name**, **Email**, **Password**, **Confirm password** (password input is hidden).

Optional non-interactive identity fields (password is **never** a CLI option):

```bash
php artisan admin:create-super-admin --name="Site Owner" --email="owner@example.com"
```

3. Log in normally at `/login` (the command does **not** create a session).
4. Complete MFA enrollment at `/security/mfa/enroll`.
5. Verify Super Admin admin access.
6. Create/assign least-privilege staff roles. Do **not** share the Super Admin account.

---

## Security requirements

| Rule | Behavior |
|------|----------|
| No hard-coded password | Operator supplies password interactively |
| Password hashing | Laravel `hashed` cast on `User` |
| RBAC only | Assigns `super_admin` via `RoleAssignmentService::bootstrapFirstSuperAdmin` |
| `users.role` | Marketplace identity only; privileges come from `user_roles` |
| Bootstrap lock | Refuses if any user already has RBAC `super_admin` |
| Concurrency | Locks `roles` / `user_roles` rows inside a transaction |
| Atomicity | User create + role assign in one DB transaction |
| Audit | `SUPER_ADMIN_BOOTSTRAPPED` (no password/hash/MFA secrets) |
| MFA | Respects `MFA_ENFORCE_ENROLLMENT`; warns if disabled |
| Production | Requires explicit yes/no confirmation |

---

## MFA

`super_admin` is in `authorization.mfa.required_roles`.

- With `MFA_ENFORCE_ENROLLMENT=true`, admin shell redirects to enrollment until MFA is configured.
- Bootstrap never prints MFA secrets or recovery codes.
- Enrollment uses the existing `/security/mfa/*` flow.

**Production recommendation:** `MFA_ENFORCE_ENROLLMENT=true`, `APP_DEBUG=false`, HTTPS, valid `APP_KEY`.

---

## Duplicate bootstrap

If a Super Admin already exists:

```
Bootstrap locked: a Super Admin already exists.
```

Further Super Admins must be assigned by an existing Super Admin through normal admin role assignment (protected role + step-up).

---

## Account recovery (no backdoor)

If all Super Admin credentials are lost, recovery requires **legitimate infrastructure access** (SSH / hosting console), not a magic URL.

Controlled recovery example (operator with server access):

1. Access the application host with authorized infrastructure credentials.
2. Open a tinker/console session on that host.
3. Identify a trusted operator user **or** create a new user through application APIs.
4. Assign RBAC `super_admin` via `RoleAssignmentService` **only if** business policy allows emergency recovery, and record a security ticket.
5. Force MFA re-enrollment.
6. Rotate credentials immediately.
7. Audit the recovery action (who, when, why).

Do **not** add hidden routes, default passwords, or undocumented CLI bypasses.

If no trusted user exists, an authorized engineer may temporarily run a **one-off, audited** maintenance script on the server that calls the same RBAC services — still no public endpoint.

---

## Dev seeders vs bootstrap

Local/dev demo Super Admin (does **not** wipe catalog data):

```bash
php artisan db:seed --class=DemoSuperAdminSeeder
```

- Email: `admin@market.com`
- Password: `password123` (dev only)
- Assigns RBAC `super_admin` via `user_roles`
- Password hashed by the User model cast

Avoid full `php artisan db:seed` on databases with data you must keep: `MarketplaceSeeder` clears catalog demo rows.

Production / staging first admin: **`php artisan admin:create-super-admin` only** (not demo seed credentials).
