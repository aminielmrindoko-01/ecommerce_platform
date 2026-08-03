# Marketplace Operations (Phase 7)

Post-purchase trust layer for SANA Market: returns, disputes, chargebacks, settlement holds, and commission configuration.

**Builds on** Phase 5 payments + Phase 6 ledger/payouts. Does **not** replace Order, Payment, Refund, or Ledger services.

**CHARGEBACK INTEGRATION:** INTERNAL ARCHITECTURE / NOT PROVIDER-CONNECTED  
**PAYMENT INTEGRATION STATUS:** SANDBOX / NOT PRODUCTION-READY  
**PAYOUT INTEGRATION STATUS:** SANDBOX / NOT PRODUCTION-READY

---

## 1. Returns

Lifecycle:

```
requested → approved → received → refunded
requested → rejected | cancelled
approved → rejected | cancelled
```

| Actor | Capabilities |
|-------|----------------|
| Customer | Request/cancel own eligible returns |
| Vendor | Approve/reject/receive **own** store returns |
| Support / ops | View + approve/reject/receive |
| Finance | Process refund after received (`refunds.create` + step-up) |

### Eligibility (`ReturnEligibilityService`)

- Authenticated customer owns the order
- Item fulfillment is `delivered`
- Order not cancelled/pending; payment not pending/failed
- Within `RETURN_WINDOW_DAYS` (default 14)
- Remaining quantity after prior non-rejected returns
- No open dispute on the item

### Inventory

Restock happens **only** on `received` when `restockable=true`, via `InventoryService` + `TYPE_RETURN` with `reference_type=return_request`. Request/approve do **not** restock.

### Refunds

`ReturnService::processRefund()` calls existing `RefundService::refund()` with:

- amount = returned line totals
- `return_request_id`
- metadata `order_item_ids` for item-scoped entitlement reversal

Ledger clawback remains `VendorEntitlementService::reverseForRefund()`.

---

## 2. Disputes

Lifecycle includes: `open`, `under_review`, `waiting_customer`, `waiting_vendor`, `resolved_customer`, `resolved_vendor`, `partially_resolved`, `closed`.

- Customer opens on own order/item
- Vendor responds only to own vendor_id disputes
- Support resolves; auditor is view-only
- Opening creates a `settlement_holds` row (`reason_code=dispute`) which **hard-blocks** payouts until released

Evidence: text messages + optional opaque `evidence_ref` (no arbitrary sensitive file uploads in this phase).

A dispute does **not** automatically refund.

---

## 3. Chargebacks

A chargeback is **not** a customer refund. It is an internal case for a provider/bank reclaim.

Statuses: `received → under_review → responded → accepted|lost|won → closed`

- Manual intake by finance (`chargebacks.create`)
- Idempotent on `(provider, provider_reference)`
- `lost` / `accepted` posts compensating ledger (`chargeback_reversal`) using `PLATFORM_CASH`, `VENDOR_PAYABLE`, `PLATFORM_REVENUE`, `REFUND_LIABILITY`
- Original ledger history is never edited

---

## 4. Settlement holds

Two mechanisms:

1. **Time-based:** `vendor_entitlements.available_at` from `FINANCE_SETTLEMENT_HOLD_HOURS`
2. **Event-based:** `settlement_holds` table for `return` | `dispute` | `chargeback` | `manual`

Payable available:

```
ledger VENDOR_PAYABLE
− open payouts
− entitlement available_at holds
− active settlement_holds amounts
```

(and `0` if vendor `financial_status` ≠ `active`)

Hard payout block: active dispute/chargeback/manual holds.

---

## 5. Commission configuration

- DB `commission_configs` (platform default + optional vendor override)
- Fallback: `config/finance.php`
- Applied at entitlement creation only; snapshots remain immutable
- Changes require `commission.manage` + step-up; vendors cannot change commission

---

## 6. Vendor financial status

`vendors.financial_status`: `active` | `payout_hold` | `financial_review` | `suspended`

Only `active` may request payouts (in addition to `canSell()`).

---

## 7. Authorization

| Permission | Intent |
|------------|--------|
| `returns.view` / `returns.approve` / `returns.manage` | Return ops |
| `disputes.view` / `respond` / `resolve` / `manage` | Dispute desk |
| `chargebacks.view` / `create` / `manage` / `resolve` | Chargeback cases |
| `settlement_holds.view` / `manage` | Hold release |
| `commission.manage` | Commission config |

`customer_support`: returns/disputes (no payouts, no commission, no ledger mutate).  
`finance_manager`: chargebacks, holds, commission, refunds.  
`auditor`: view-only.  
Payout SoD (`FINANCE_PAYOUT_SOD`) unchanged.

---

## 8. Audit events

`RETURN_REQUESTED`, `RETURN_APPROVED`, `RETURN_REJECTED`, `RETURN_RECEIVED`, `RETURN_REFUNDED`  
`DISPUTE_OPENED`, `DISPUTE_RESOLVED`, `DISPUTE_CLOSED`  
`CHARGEBACK_RECEIVED`, `CHARGEBACK_UPDATED`, `CHARGEBACK_RESOLVED`  
`SETTLEMENT_HOLD_CREATED`, `SETTLEMENT_HOLD_RELEASED`  
`COMMISSION_CONFIG_UPDATED`  
`VENDOR_PAYOUT_HOLD`, `VENDOR_PAYOUT_ELIGIBLE`

---

## 9. Configuration

```
RETURN_WINDOW_DAYS=14
FINANCE_SETTLEMENT_HOLD_HOURS=0
FINANCE_COMMISSION_RATE=0.10
CHARGEBACK_PROVIDER=internal
FINANCE_PAYOUT_SOD=true
```

---

## 10. Remaining limitations

- No live chargeback provider / bank webhook
- No return shipping carrier integration
- Evidence is reference-only (no dedicated secure evidence vault)
- Category/promotional commission scopes are schema-ready via `commission_configs.scope` but UI focuses on platform (+ service API for vendor override)
