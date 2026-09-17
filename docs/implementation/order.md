# Implementation Order & Progress

This log tracks SRS requirement execution (see `SRS.md`). Only entries with **Status: Implemented** have merged code.

## Phase Status

| Phase | Status |
|---|---|
| 0 — Foundation & Schema Hardening | complete |
| 1 — MVP Accounting Core | complete |
| 2 — Ledger & Core Reporting | complete |
| 3 — Approval & Security Controls | complete |
| 4 — Accounts Receivable | in_progress |
| 5 — Accounts Payable | not_started |
| 6 — Banking | not_started |
| 7 — Enterprise & Operational Features | not_started |
| 8 — Reporting Optimization & Derived Performance | not_started |
| 9 — Reliability & Production Hardening | not_started |

## Phase 4 — Accounts Receivable

### 4.4 Payment Allocation (SRS §7.25; §0.9; INV-010; BR-012/BR-013)

**Status:** Implemented

**Scope:** Allocate draft payments against one or more invoices (org/customer/currency-matched). Transaction-scoped `FOR UPDATE` locks on payment + invoices in deterministic ID order (§0.9). Over-allocation prevented via `SUM(allocations) <= payment.amount` and `<= invoice.outstanding`. Reconcile-on-post: posted allocations flip invoice status (`partially_paid`/`paid`) and write audit events. Currency rule: same-currency only (no cross-rate allocation yet).

**Files:** `AllocateCustomerPayment`, `PostCustomerPayment` (reconcile-on-post), `CustomerPaymentController::allocate()`, `AllocateCustomerPaymentRequest`, `AllocationDialog.jsx`, `CustomerPaymentPolicy::allocate()`, `allocate-payments` permission.

**Tests:** `tests/Feature/CustomerPaymentAllocationTest.php` — 17 tests / 70 assertions. Related suites all green.

### 4.5 Customer Credit Notes (SRS §7.27)

**Status:** Not started

**Scope:** Credit note reduces customer receivables (DR Revenue / CR A/R) with appropriate accounting entries; likely mirrors invoice lifecycle (draft → posted through `PostJournalEntry`, §0.7 boundary) and reduces invoice outstanding.

**Next:** AR Aging (§7.26).

---
