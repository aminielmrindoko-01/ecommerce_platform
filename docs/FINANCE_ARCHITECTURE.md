# Finance Architecture (Phase 6)

**PAYOUT INTEGRATION STATUS: SANDBOX / NOT PRODUCTION-READY**

SANA Market finances sit **on top of** Phase 5 payments. Do not replace `PaymentService` / `RefundService`.

```
Payment SUCCESS
→ Vendor entitlements (per order item)
→ Double-entry ledger post
→ Vendor payable (derived)
→ Payout request / approve / process (sandbox)
```

---

## 1. Chart of accounts (TZS)

| Code | Type | Purpose |
|------|------|---------|
| `PLATFORM_CASH` | asset | Customer payments received / payouts sent |
| `VENDOR_PAYABLE` | liability | Amount owed to vendors (tagged `vendor_id` on lines) |
| `PLATFORM_REVENUE` | revenue | Commission + tax/shipping residual / discount contra |
| `REFUND_LIABILITY` | liability | Reserved for future chargeback flows |
| `PAYOUT_CLEARING` | liability | Reserved for multi-step clearing adapters |

---

## 2. Ledger model

- `ledger_transactions` — journal header (append-only)
- `ledger_entries` — debit **or** credit lines
- Writer: `LedgerService::post()` only

### Invariants

1. `SUM(debits) == SUM(credits)` (enforced)
2. Single currency per transaction (TZS)
3. No negative amounts
4. Valid account codes only
5. Idempotency via unique `idempotency_key`
6. Corrections = compensating transactions (never edit history)

---

## 3. Vendor entitlements

On verified payment (`paid`):

For each `order_item`:

- `gross` = snapshot `price × quantity`
- commission from **config snapshot** (`finance.commission`, default 10%)
- `net` = gross − commission
- stored on `vendor_entitlements` with frozen rate/type/snapshot JSON

Multi-vendor orders produce **separate** entitlements per item vendor.

---

## 4. Commission

Config: `config/finance.php`

- `percentage` (default) or `fixed`
- Applied to item subtotals (not tax/shipping)
- Historical rows keep their snapshot — never re-priced from live config

---

## 5. Refund integration

`RefundService` (existing) remains payment authority.

After refund completes, `VendorEntitlementService::reverseForRefund()`:

- Allocates refund across remaining item gross shares
- Claws back net + commission using **original** rates
- Posts balancing `refund_reversal` ledger transaction
- Idempotent key: `refund-reversal:{refund_id}`

---

## 6. Vendor payable

**Authoritative:** ledger `VENDOR_PAYABLE` credits − debits for `vendor_id`.

**Available to withdraw:**

```
ledger_payable
− open payouts (pending|approved|processing)
− entitlements still in settlement hold
```

No mutable `vendors.balance`.

---

## 7. Payout lifecycle

```
pending → approved → processing → completed
pending → rejected|cancelled
processing → failed
```

- Request: vendor (own store) or finance ops
- Approve / reject / process: finance permissions + **step-up**
- Separation of duties (config): approver ≠ processor (unless Super Admin)
- Sandbox stub auto-completes after process and posts:

```
DR VENDOR_PAYABLE
CR PLATFORM_CASH
```

---

## 8. Payout provider

`PayoutGatewayInterface` → `StubPayoutGateway`

- `supportsLivePayouts() = false`
- Never stores bank passwords / PINs / cards
- Destination stored only as opaque `destination_token`

---

## 9. Idempotency & concurrency

- Payout `idempotency_key` unique
- Vendor row `lockForUpdate` during request
- Concurrent full-balance requests: one wins

---

## 10. Reconciliation

`PayoutReconciliationService` flags provider↔local mismatches via existing payment reconciliation channel + `PAYOUT_RECONCILIATION_REQUIRED` audit. No silent rewrites.

---

## 11. RBAC

| Permission | Intent |
|------------|--------|
| `ledger.view` | Read journal |
| `finance.reports.view` | Aggregates |
| `payouts.view` | List payouts/payables |
| `payouts.approve` / `payouts.reject` | Approval |
| `payouts.process` | Execute (sandbox) |

`finance_manager` has full finance ops. `auditor` is view-only. Vendors see **own** finance only.

---

## 12. Configuration

```
FINANCE_CURRENCY=TZS
FINANCE_COMMISSION_RATE=0.10
FINANCE_SETTLEMENT_HOLD_HOURS=0
PAYOUT_GATEWAY=stub
FINANCE_PAYOUT_SOD=true
```

Never commit provider secrets.

---

## 13. Chargeback reversals (Phase 7)

Chargebacks are **not** customer refunds. Internal cases (`ChargebackService`) post compensating journals on `lost` / `accepted`:

```
DR VENDOR_PAYABLE (remaining net)
DR PLATFORM_REVENUE (remaining commission)
DR/CR REFUND_LIABILITY (balancing residual)
CR PLATFORM_CASH (chargeback amount)
```

Idempotency key: `chargeback-reversal:{id}`. History is never rewritten.

**CHARGEBACK INTEGRATION:** INTERNAL ARCHITECTURE / NOT PROVIDER-CONNECTED

## 14. Settlement holds (Phase 7)

- Time-based: `vendor_entitlements.available_at` (`FINANCE_SETTLEMENT_HOLD_HOURS`)
- Event-based: `settlement_holds` for returns / disputes / chargebacks / manual
- Available payable subtracts both; dispute/chargeback/manual holds also hard-block payout requests
- See `docs/MARKETPLACE_OPERATIONS.md`

## 15. Commission configuration (Phase 7)

- Live rules: `commission_configs` (platform default, optional vendor override) via `CommissionConfigService`
- Fallback: `config/finance.php`
- Entitlements store immutable snapshots — changing config never recalculates history
- Requires `commission.manage` + step-up

## 16. Financial restrictions

`vendors.financial_status`: `active` | `payout_hold` | `financial_review` | `suspended`  
Only `active` (+ approved sell status) may request payouts.

## 17. Status banner

**PAYMENT INTEGRATION STATUS:** SANDBOX / NOT PRODUCTION-READY (Phase 5)  
**PAYOUT INTEGRATION STATUS:** SANDBOX / NOT PRODUCTION-READY (Phase 6)  
**CHARGEBACK INTEGRATION:** INTERNAL ARCHITECTURE / NOT PROVIDER-CONNECTED (Phase 7)
