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

**Status:** Implemented

**Scope:** Credit note references a posted invoice (BR-014) and offsets customer receivables through the ledger boundary (`PostJournalEntry`, §0.7): CR the customer's receivable account for the total, DR the linked invoice's revenue accounts (proportional to line net revenue) and tax payable accounts (proportional to line tax), with residual absorbed on the last line of each group so the entry balances exactly. Lifecycle draft → posted (maker-checker) or draft → voided; invoice balances are not mutated (AR offset belongs to §7.26). Over-credit prevented via `total <= invoice.balance_due - SUM(non-voided credit notes)` on create and `<= balance_due - SUM(posted)` under `FOR UPDATE` on post. Currency derived server-side from the invoice; per-org sequential numbering `CN-000001`; idempotency-key support; audit events `customer-credit-note.{created,posted,voided}`.

**Files:** `CustomerCreditNoteStatus`, `CustomerCreditNote`, `CustomerCreditNoteFactory`, `CreateCustomerCreditNote`, `PostCustomerCreditNote`, `VoidCustomerCreditNote`, `CustomerCreditNotePolicy`, `StoreCustomerCreditNoteRequest`, `VoidCustomerCreditNoteRequest`, `CustomerCreditNoteController`, `CreditNotes/Index.jsx`, `CreditNotes/CustomerCreditNoteForm.jsx`, permissions `view/create/approve/void-credit-notes`.

**Tests:** `tests/Feature/CustomerCreditNoteTest.php` — 24 tests / 73 assertions.

**Next:** AR Aging (§7.26).

---
