# Payment Architecture (Phase 5)

**PAYMENT INTEGRATION STATUS: SANDBOX / NOT PRODUCTION-READY**

SANA Market payments are provider-independent. Live charging is fail-closed unless an explicit sandbox/production provider is configured. Do **not** invent a second payment system — extend `PaymentService` + `PaymentGatewayInterface`.

---

## 1. Conceptual flow

```
Customer → Order → Payment Attempt → Provider
→ Provider Response → Server Verification
→ Payment State → Inventory Commit/Release → Fulfillment
```

Frontend “success” is **never** proof of payment.

---

## 2. Components

| Piece | Role |
|-------|------|
| `PaymentGatewayInterface` | Provider adapter boundary |
| `PaymentGatewayManager` | Resolve stub / PesaPal / future drivers |
| `PaymentService` | **Only** authority for payment status mutations |
| `OrderInventorySettlement` | Commit/release reserved stock from payment outcomes |
| `RefundService` | Partial/full refunds with cumulative caps |
| `PaymentReconciliationService` | Flag local↔provider mismatches (no silent money edits) |
| `PaymentNotificationReceipt` | Webhook/IPN replay protection |

---

## 3. Payment states

Canonical DB values (SUCCEEDED = `paid`):

| State | Meaning |
|-------|---------|
| `pending` | Attempt created |
| `initiated` | Optional explicit hop after provider init |
| `processing` | Provider / ops in flight |
| `paid` | Verified success |
| `failed` / `cancelled` / `expired` | Terminal unsuccessful |
| `partially_refunded` / `refunded` | Post-success refunds |

Transitions are enforced in `PaymentService` (not client-writable).

Checkout stamps `initiated_at` on gateway init without requiring a status hop (keeps admin `pending → processing → paid` UX stable).

---

## 4. Payment attempts

Table: `payment_transactions` (one row = one attempt).

Fields include: `attempt_number`, `idempotency_key` (unique), `amount`, `currency`, `status`, `provider`, `provider_reference`, `failure_code`, `failure_reason`, `initiated_at`, `completed_at`, `refunded_amount`, `paid_at`.

After a terminal failure/expiry, `createAttempt()` starts attempt N+1 (and re-reserves inventory if previously released).

Never store card numbers, CVV, PINs, or provider secrets.

---

## 5. Money & currency

- Server calculates payable amount from `orders.total_price` (bcmath).
- Currency stored on **Order** and **PaymentTransaction** (default **TZS**).
- Client `amount` / `currency` / `status` are ignored or rejected.

---

## 6. Idempotency

| Layer | Mechanism |
|-------|-----------|
| Checkout order place | `checkout_idempotency_keys` |
| Payment attempt create | `payment_transactions.idempotency_key` unique |
| Provider reference | unique `provider_reference` + paid replay no-op |
| Webhooks / IPN | `payment_notification_receipts.notification_key` unique + row lock |

---

## 7. Inventory ↔ payment

| Event | Inventory |
|-------|-----------|
| Order placed | `reserve` → `inventory_state=reserved` |
| Payment `paid` (verified) | `commitSaleFromReserved` → `committed` |
| Payment failed/cancelled/expired | `releaseReserved` → `released` |
| Duplicate `paid` | Idempotent — no double-commit |
| Unpaid cancel | Release reservation |
| Paid cancel | Return adjust (restock) |

`orders.inventory_state` prevents impossible double-commit/release.

---

## 8. Webhook security (PesaPal sandbox)

Present:

- Server re-verification via provider API (`GetTransactionStatus`)
- Merchant reference `hash_equals`
- Amount/currency checks vs order
- Replay protection via notification receipts
- Redirect host allow-list

Not present (document limitation):

- Cryptographic HMAC webhook signatures (provider model is verify-by-API)

---

## 9. Refunds

`payment_refunds` append-only ledger.

Statuses: `requested → approved → processing → completed` (also `failed` / `cancelled`).

Rules:

- Requires `refunds.create` (or `orders.refund`)
- Route uses existing **step-up** middleware
- Amount ≤ remaining refundable (`amount - refunded_amount`)
- Currency must match
- Updates payment to `partially_refunded` / `refunded`

Provider-native refund APIs are **not** invented — foundation path is manual/admin controlled.

---

## 10. Reconciliation

`payment_reconciliations` records mismatches:

- Provider SUCCESS vs local PENDING
- Local SUCCESS vs provider disagreement

Creates `PAYMENT_RECONCILIATION_REQUIRED` audit + security event. **Does not** auto-mutate balances.

---

## 11. Authorization

| Actor | Capability |
|-------|------------|
| Customer | Initiate/view own payments; never mutate status/amount |
| `finance_manager` / `admin` | `payments.manage`, refunds (step-up) |
| `order_manager` | orders only — **no** payment mutation |
| `customer_support` | payment **view** only |
| Vendor | **no** platform payment dashboard |

Routes:

- `GET /admin/payments` — `payments.view` \| `transactions.view`
- `POST /admin/payments/{payment}/refund` — `refunds.create` + `stepup`
- `PATCH /admin/orders/{order}/payment` — `payments.manage`
- `GET /account/payments` — own attempts

---

## 12. Audit events

`PAYMENT_ATTEMPT_CREATED`, `PAYMENT_INITIATED`, `PAYMENT_PROCESSING`, `PAYMENT_SUCCEEDED`, `PAYMENT_FAILED`, `PAYMENT_CANCELLED`, `PAYMENT_EXPIRED`, `REFUND_REQUESTED`, `REFUND_APPROVED`, `REFUND_COMPLETED`, `REFUND_FAILED`, `PAYMENT_RECONCILIATION_REQUIRED`

Never log cards, CVV, passwords, MFA secrets, tokens, or provider secrets.

---

## 13. Provider integration status

| Provider | Status |
|----------|--------|
| Stub | Active non-charging default (`PAYMENT_GATEWAY=stub`) |
| PesaPal | Sandbox adapter only (`PESAPAL_ENV=sandbox`) |
| M-Pesa / Stripe / PayPal / … | Config placeholders — not implemented |

**Do not claim real-money production readiness** without a securely configured, verified provider.

### Sandbox env (example)

```
PAYMENT_GATEWAY=stub
PAYMENT_CURRENCY=TZS
PAYMENT_PESAPAL_ENABLED=false
PESAPAL_ENV=sandbox
# PESAPAL_CONSUMER_KEY=
# PESAPAL_CONSUMER_SECRET=
```

Never commit secrets to Git.

---

## 14. Admin / customer UX

- Admin Payments dashboard: search/filter/paginate attempts; refund action when permitted
- Customer Payment history: own attempts only (amount, currency, status, order ref)
- Vendors do not see platform-wide payments
