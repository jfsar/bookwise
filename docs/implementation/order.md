# Implementation Order & Progress

This log tracks SRS requirement execution (see `SRS.md`). Only entries with **Status: Implemented** have merged code.

## Phase Status

| Phase | Status |
|---|---|
| 0 — Foundation & Schema Hardening | complete |
| 1 — MVP Accounting Core | complete |
| 2 — Ledger & Core Reporting | complete |
| 3 — Approval & Security Controls | complete |
| 4 — Accounts Receivable | complete |
| 5 — Accounts Payable | in_progress |
| 6 — Banking | not_started |
| 7 — Enterprise & Operational Features | not_started |
| 8 — Reporting Optimization & Derived Performance | not_started |
| 9 — Reliability & Production Hardening | not_started |

## Phase 5 — Accounts Payable

**Current: Vendor Bills (§7.29) — Status: Implemented.**

Scope: bill master data (draft → posted → partially paid → paid) with tenant-safe composite keys, concurrency-safe per-org numbering (`vendor_bill_number_sequence`, `BILL-NNNNNN`), maker-checker posting through the accounting boundary (DR expense + input tax, CR accounts payable per §7.30), proportional draft reversal on voiding posted bills (§7.29 lifecycle), idempotent store, granular policy, audit, seeded permissions, Inertia UI.

- Files: `app/Models/{VendorBill,VendorBillLine}.php`, `app/Enums/VendorBillStatus.php`, `app/Actions/VendorBill/{Create,Post,Void}VendorBill.php`, `app/Policies/VendorBillPolicy.php`, `app/Http/Requests/{Store,Void}VendorBillRequest.php`, `app/Http/Controllers/VendorBillController.php`, `routes/web.php`, `database/factories/{VendorBill,VendorBillLine}Factory.php`, `database/seeders/PermissionSeeder.php`, migration `2026_09_18_000100_add_vendor_bill_number_sequence_to_organizations_table.php`, `resources/js/Pages/Bills/{Index,VendorBillForm}.jsx`, `resources/js/Layouts/AppLayout.jsx`.
- Tests: `tests/Feature/VendorBillTest.php` (24 tests, 80 assertions) — passing, including §7.30 accounting-mapping assertions. Seeder safety suites (SeederSafety, RoleManagement, TargetUserSeeder, 13 tests) passing. `npm run build` clean.
- Migrations applied on dev (`pgsql_direct`); `PermissionSeeder` re-seeded idempotently.

## Vendor Payments (§7.31) — Status: Implemented

Scope: vendor payment lifecycle (draft → posted → voided) that reduces accounts payable per §7.31 (DR accounts payable, CR cash/bank). Concurrency-safe per-org numbering (`vendor_payment_number_sequence`, `VPAY-NNNNNN`), tenant-safe debit-source FK (`fk_vpay_org_cash_acct`) + CHECK enforcement, maker-checker posting through the accounting boundary (DR vendor payable account, CR cash account, Scenario 6), void of draft/posted payments with `ReverseJournalEntry` reversal of posted entries, idempotent store, granular vendor-specific permissions (`view/create/approve/void-vendor-payments`), audit, Inertia UI. Payment allocation + vendor-bill settlement deferred to the next item (Payment Allocation).

- Files: `app/Models/{VendorPayment,VendorPaymentAllocation}.php`, `app/Enums/VendorPaymentStatus.php`, `app/Actions/VendorPayment/{Create,Post,Void}VendorPayment.php`, `app/Policies/VendorPaymentPolicy.php`, `app/Http/Requests/{Store,Void}VendorPaymentRequest.php`, `app/Http/Controllers/VendorPaymentController.php`, `routes/web.php`, `database/factories/VendorPaymentFactory.php`, `database/seeders/PermissionSeeder.php`, migrations `2026_09_18_000200/000201/000202_*.php`, `resources/js/Pages/VendorPayments/{Index,VendorPaymentForm}.jsx`, `resources/js/Layouts/AppLayout.jsx`.
- Tests: `tests/Feature/VendorPaymentTest.php` (24 tests, 83 assertions) — passing, including §7.31 AP-debit/cash-credit line assertions. Migrations applied on dev (`pgsql_direct`); `PermissionSeeder` re-seeded idempotently.

**Next:** Payment Allocation (vendor payments → vendor bills) and Vendor Debit Notes (§7.33).