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
| 5 — Accounts Payable | complete |
| 6 — Banking | not_started |
| 7 — Enterprise & Operational Features | not_started |
| 8 — Reporting Optimization & Derived Performance | not_started |
| 9 — Reliability & Production Hardening | not_started |

## Phase 5 — Accounts Payable — Status: complete

Implemented (migrations applied on `pgsql_direct`; suites passing):

- **Vendor Bills (§7.29–§7.30)**: `VendorBillTest` (24 tests, 80 assertions).
- **Vendor Payments (§7.31)**: `VendorPaymentTest` (24 tests, 83 assertions).
- **Payment Allocation** (bills settlement): `VendorPaymentAllocationTest` (18 tests, 80 assertions).
- **Vendor Debit Notes** (§7.27 semantics applied to payables per §0.18): `VendorDebitNoteTest` (24 tests, 76 assertions).
- **AP Aging** (§67; buckets from §7.26 applied to payables): `ApAgingReportTest` (10 tests, 70 assertions). Files: `app/Reports/ApAgingReport.php`, `app/Enums/ReportType.php` (`ApAging` case), `app/Reports/ReportFactory.php`, `app/Reports/ReportCsvExporter.php`, `app/Http/Controllers/ReportController.php`, `routes/web.php` (`reports.ap-aging`), `resources/js/Pages/Reports/ApAging.jsx`, `resources/js/Layouts/AppLayout.jsx`.

**Next:** Phase 6 — Banking (Bank Accounts §7.32, Bank Transactions §7.33, Reconciliation §7.34).

Note: Payment Allocation changes (Phase 5) are still uncommitted in the working tree.