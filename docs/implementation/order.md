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

**Current: Vendors (§7.28) — Status: Implemented.**

Scope: vendor master data CRUD with tenant-safe composite keys, concurrency-safe per-org numbering (`vendor_number_sequence`), granular policy, audit, Inertia UI, and seeded permissions.

- Files: `app/Models/Vendor.php`, `app/Actions/Vendor/{Create,Update}Vendor.php`, `app/Policies/VendorPolicy.php`, `app/Http/Requests/{Store,Update}VendorRequest.php`, `app/Http/Controllers/VendorController.php`, `routes/web.php`, `database/factories/VendorFactory.php`, `database/seeders/PermissionSeeder.php`, migration `2026_09_18_000000_add_vendor_number_sequence_to_organizations_table.php`, `resources/js/Pages/Vendors/{Index,VendorForm}.jsx`, `resources/js/Layouts/AppLayout.jsx`.
- Tests: `tests/Feature/VendorTest.php` (18 tests) — passing. Related suites (Customer, SeederSafety, TargetUserSeeder, RoleManagement) passing. `npm run build` clean.
- Migrations applied on dev (`pgsql_direct`); `PermissionSeeder` re-seeded idempotently.

**Next:** Vendor Bills (§7.29).