# Order & Marketplace Architecture (Phase 4)

SANA Market is a **multi-vendor** marketplace. A single customer checkout creates one coherent **Order** with many **OrderItems**, each preserving the selling vendor.

Do **not** introduce a second authorization stack. Extend existing RBAC + policies + ownership checks.

---

## 1. Vendor architecture

| Concept | Implementation |
|---------|----------------|
| Store entity | `vendors` (`user_id` 1:1) |
| Lifecycle | `vendors.status`: `pending` → `under_review` → `approved` / `rejected` / `suspended` / `inactive` |
| Legacy badge | `is_verified` kept in sync (`approved` ⇒ true) |
| Application | `VendorApplicationController` + `VendorLifecycleService::apply` |
| Admin review | `Admin\VendorController` + `VendorLifecycleService::transition` |
| Sell gate | `VendorMiddleware` requires `vendor.canSell()` (approved) |
| Ownership | Always `auth()->user()->vendor` — never trust client `vendor_id` |

### Permissions (least privilege)

| Role | Vendor capabilities |
|------|---------------------|
| `vendor_manager` | view / create / update / approve / reject / suspend |
| `admin` / `super_admin` | full vendor ops |
| `vendor` | own catalog / inventory / order items after approval |

`vendor_manager` does **not** receive `roles.update`, `permissions.assign`, or `payouts.process`.

### Performance metrics

`VendorPerformanceService` reports **real** aggregates only:

- total / published products
- order-item counts (total / delivered / cancelled)
- sales value (TZS)
- store `rating_avg` when present

Unavailable (deferred): return rate, fulfillment SLA scores.

---

## 2. Order architecture

```
ORDER (customer-owned header)
├── OrderItem → Vendor A (snapshot: name, sku, price, vendor_id, store name)
├── OrderItem → Vendor B
└── OrderItem → Vendor A
```

There is **no** authoritative `orders.vendor_id`. Multi-vendor carts are first-class.

### Snapshots on `order_items`

At purchase, `OrderItem::recordPurchase` stores:

- `vendor_id`, `vendor_store_name`
- `product_name`, `product_sku`
- `price`, `quantity`

Historical orders do not drift when catalog rows change.

### Currency

`orders.currency` defaults to **TZS**. Totals use `bcmul` / `bcadd` (no float money math). Multi-currency can later extend calculation helpers without rewriting controllers.

---

## 3. Order lifecycle (header)

Controlled by `OrderStateMachine` (not arbitrary client status writes):

```
pending → confirmed → processing → ready_for_fulfillment → shipped → delivered
                ↘ cancelled                          ↗
paid (legacy) → confirmed / processing / … / cancelled
delivered / completed → refunded (ops)
```

- **Payment** remains on `orders.payment_status` via `PaymentService` only.
- Clients **cannot** set order status to `paid` (validation uses `MUTABLE_STATUSES`).
- When payment becomes `paid`, PaymentService soft-syncs header status `pending|paid` → **`confirmed`**.

Item-level fulfillment remains `OrderItemFulfillmentService` (vendor/admin).

### Cancellation

`OrderService::cancel`:

| Actor | Rule |
|-------|------|
| Customer (`orders.view`, owner) | `pending` / `confirmed` / legacy `paid` |
| Staff (`orders.cancel` + `orders.manage_any`) | same cancellable set via service |
| Delivered / shipped | denied |

Cancellation restores stock via `InventoryService::adjust` (`return`) and audits `ORDER_CANCELLED`.

---

## 4. Order-item ownership

Every vendor-facing item action checks:

`orderItem.vendor_id === auth.vendor.id` (preferred snapshot)

with fallback to `product.vendor_id` for pre-migration rows.

`Vendor\OrderController` eager-loads **only** the authenticated vendor’s items — preventing cross-vendor leakage on multi-vendor orders.

---

## 5. Inventory reservation

Helpers (Phase 3 + 4):

| Method | Effect |
|--------|--------|
| `reserve` | available ↓, reserved ↑ (locked) |
| `releaseReserved` | reserved ↓, available ↑ |
| `commitSaleFromReserved` | reserved ↓ (sale movement; available unchanged) |

### Current checkout (Phase 5)

Inside one DB transaction at place:

1. Validate cart (published, approved vendor, stock, server prices)
2. Create order + snapshot items
3. `reserve` only → `orders.inventory_state = reserved`
4. Audit `ORDER_CREATED`

Then payment attempt is created; gateway init stamps `initiated_at`.

```
payment SUCCESS (verified) → commitSaleFromReserved → inventory_state=committed → order confirmed
payment FAILED / EXPIRED / unpaid cancel → releaseReserved → inventory_state=released
duplicate paid webhook → idempotent (no double-commit)
```

See `docs/PAYMENT_ARCHITECTURE.md`.

Concurrency: `lockForUpdate` on product rows; second buyer with stock=1 fails.

---

## 6. Cart & checkout security

- Session cart is a hint only. Prices / totals / vendor ids from the client are ignored.
- Checkout rejects payloads containing `user_id`, `customer_id`, `vendor_id`, `price`, `subtotal`, `total`, `status`, `payment_status`.
- `OrderService` recalculates subtotal, discount, shipping, tax, total with bcmath.
- Unpublished products and non-approved vendors cannot be purchased.

---

## 7. Authorization rules

Pipeline: **Auth → Permission → Ownership → Validation → Business rule → Transaction → Audit → Response**

| Actor | Orders |
|-------|--------|
| Customer | own orders only (`abort` 403 on IDOR) |
| Vendor | own items only |
| `order_manager` | view/update/cancel + `manage_any`; no RBAC admin |
| `customer_support` | view customers/orders; no update/roles/MFA |
| Finance | payments via `PaymentService` / `PaymentGatewayInterface` |

---

## 8. Audit events

Via existing `AuditLogger` (no parallel audit system):

- `VENDOR_APPLICATION_SUBMITTED`, `VENDOR_APPROVED`, `VENDOR_REJECTED`, `VENDOR_SUSPENDED`, …
- `ORDER_CREATED`, `ORDER_CONFIRMED`, `ORDER_PROCESSING`, `ORDER_READY_FOR_FULFILLMENT`, `ORDER_SHIPPED`, `ORDER_DELIVERED`, `ORDER_CANCELLED`, `ORDER_REFUNDED`

Never log passwords, MFA secrets, tokens, or card data.

---

## 9. Payment boundary

- Contract: `App\Contracts\PaymentGatewayInterface`
- Orchestration: `PaymentGatewayManager` + `PaymentService`
- Controllers must not embed provider protocols or mark orders paid directly.
- No fake live charging; stub / sandbox gateways remain non-charging unless explicitly enabled.

---

## 10. Dashboards

| Surface | Scope |
|---------|--------|
| Admin orders | search / status / payment filters, controlled next statuses, item fulfillment |
| Vendor orders | vendor items + minimized shipping fields for fulfillment |
| Customer orders | own history, snapshots, cancel when allowed |

---

## Security assumptions

1. RBAC permissions are the capability layer; policies enforce tenancy.
2. `users.role` never widens beyond assigned RBAC roles once the catalog is seeded.
3. Inventory oversell prevention is server-side (row locks), not UI.
4. Historical orders are soft-lifecycle records — not physically deleted.
