# Implementation Order & Progress

This log records the ordered execution of SRS requirements (see `SRS.md`). Only entries with
**Status: Implemented** have merged code.

---

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

---

## Phase 3 - Approval & Security Controls

### 3.1 Approval Workflow & Requests engine (SRS 0.8, 7.36, 7.37; V1.1 #40)

**Status:** Implemented

**Scope of this slice**

- Approval *workflow/rule/step* configurability and an *approval request* runtime, wired to
  journal entries, replacing raw per-entry single-approver flow only when a matching workflow
  exists. When no workflow matches, legacy single-approver behavior is preserved.
- Every approval action is immutable and references the request step (SRS 0.8).
- Maker-checker: the requester can never be an approver of the same request step.
- Parallel steps enforce distinct-approver minimums; sequential steps enforce prior-step
  approvals (ladder); no parallel-with-sequential laddering yet (V1.1 refinement).

**Tests:** `tests/Feature/ApprovalWorkflowTest.php` — 19 tests.

---

### 3.2 PostJournalEntry maker-checker hardening: `creator != final poster` (SRS 0.8; §7.14)

**Status:** Implemented

**SRS / V1.1 references**

- §0.8 segregation of duties — default production rule `creator != final poster`
- §7.14 Journal Posting
- V1.1 invariants: INV-009 lifecycle monotonicity (posting path), AP-006 authorization before mutation

**Implementation evidence**

- `app/Actions/JournalEntry/PostJournalEntry.php`: rejects with `Maker-checker: The final
  poster cannot be the same person who created the entry.` when `entry.created_by === poster.id`,
  enforced inside the transaction. This closes a V1.1 Segregation-of-Duties gap even when no
  approval workflow matches (direct-post path).
- `app/Policies/JournalEntryPolicy::post()`: denies the HTTP posting route for the entry creator,
  so the maker-checker rule holds at the authorization boundary, not only in the domain action.

**Tests added/updated**

- `tests/Feature/JournalEntryTest.php` — `test_maker_checker_prevents_creator_posting`;
  `test_cannot_post_to_closed_period` now posts as a distinct user.
- `tests/Feature/JournalEntryActionsTest.php` — `test_post_forbids_creator_with_permission`
  (HTTP 403), `test_post_allows_non_creator_with_permission`.
- `tests/Feature/ReportsTest.php` and `tests/Feature/DashboardTest.php` — seed helpers post via a
  distinct user so ledger fixtures still post.

**Tests executed**

- Full suite at verification: **94 tests / 317 assertions, passing.**

**Notes / gaps**

- `ApproveJournalEntry` enforces `submitted_by != approver` (requester), not literally
  `created_by != approver`. Equivalent when the creator submits their own entry, which the
  lifecycle requires today; flagged as a refinement rather than a blocker.

---

### 3.3 Audit-log tamper evidence / chain verification tooling (SRS 0.29, 7.37; §0.41 checklist)

**Status:** Implemented

**SRS / V1.1 references**

- §7.37 Audit Logging
- §0.29 V1.1 audit requirements (append-only records; tamper-evidence where configured)
- §0.41 V1.1 implementation checklist — "Audit logs are append-only and tamper-evident"
- V1.1 INV-012 (derived correctness), AP-004/AP-007

**Implementation evidence**

- `app/Services/AuditLogService.php`
  - `record()` now persists the **exact** `created_at` value it hashes (previously the DB
    `useCurrent()` default could differ by a second, making a clean chain fail recomputation).
    Payload serialization extracted into `payload()`.
  - New `verify(Organization $organization): AuditLogVerificationResult` recomputes the per-org
    SHA-256 chain with a lazy `cursor()` and reports every record that is out of sequence, breaks
    the previous-hash link, or whose `record_hash` does not reproduce.
- `app/Services/AuditLogVerificationResult.php` — immutable result `{valid, checkedCount, failures}`.
- Append-only surface: the application exposes no route, permission, or controller action to
  update or delete audit rows (only `viewAny` with `view-audit-logs`); the verifier provides the
  tamper evidence required by V1.1. DB-level UPDATE/DELETE trigger protection is intentionally not
  added so mutating a row in a test/forensic context remains detectable rather than impossible.

**Tests added/updated**

- `tests/Feature/AuditLogTest.php` (+7): empty-org valid, valid chain + checked count, tampered
  record hash, broken previous-hash link, corrupted genesis hash, deleted record, and
  cross-organization isolation.

**Tests executed**

- Full suite: **101 tests / 337 assertions, passing.**

---

### 3.4 Sensitive-route re-authentication hook (SRS 0.27; Fortify 2FA coverage)

**Status:** Implemented

**SRS / V1.1 references**

- §0.27 reauthentication: require recent authentication for close period, reopen period, change
  bank details, change roles/permissions, change accounting configuration, and exporting highly
  sensitive financial data
- V1.1 §0.19 weak-authentication cleanups (reauthentication) and AP-006 authorization-before-mutation

**Implementation evidence**

- Sensitive routes now carry Laravel's `password.confirm` middleware (3-hour window,
  `config/auth.php` `password_timeout`, enforced by `RequirePassword`); unconfirmed/non-recent
  requests are redirected to `/user/confirm-password`, JSON requests get 423:
  - `accounts.store`, `accounts.update`, `fiscal-years.store` — accounting configuration
  - `accounting-periods.close`, `accounting-periods.reopen` — period controls
  - `settings.roles.store/update/destroy`, `settings.users.store/update` — roles/permissions
  - `organizations.update` — organization settings
  - `reports.export`, `reports.download` — export of sensitive financial data
- Bank-details routes do not exist yet (Phase 6 Banking); the re-auth pattern applies there when
  added. Read-only report pages and session management were intentionally left out of the hook.
- Fortify 2FA + passkeys are enabled. New automated coverage proves a 2FA-enabled user is
  challenged at login (`/two-factor-challenge`) and can authenticate only after a valid TOTP code;
  passkey registration/verification relies on the browser/WebAuthn and has no automated test.

**Tests added/updated**

- `tests/TestCase.php` — `confirmPassword()` helper (posts `/user/confirm-password`).
- `tests/Feature/ReauthenticationTest.php` (new, 17 tests): every sensitive route redirects to
  `/user/confirm-password` without confirmation; pass-through proof after confirmation (policy 403
  where permission-gated, validation errors where requests are open); an 11-hour-old confirmation
  no longer passes.
- `tests/Feature/RoleManagementTest.php`, `tests/Feature/UserManagementTest.php`,
  `tests/Feature/OrganizationTest.php`, `tests/Feature/ReportsTest.php` — confirm password before
  acting so their permission assertions still apply.
- `tests/Feature/AuthenticationTest.php` (+2): 2FA challenge on login; login completion with a
  valid TOTP code.

**Tests executed**

- Full suite: **120 tests / 393 assertions, passing.**

---

## Remaining Phase 3 items

- None outstanding for Phase 3 as recorded; Phase 3 is effectively complete for the slices defined
  in the V1.1 order.

---

## Phase 4 - Accounts Receivable

### 4.1 Customers (SRS §7.22; §0.5.1; §0.18)

**Status:** Implemented

**SRS / V1.1 references**

- §7.22 Customers (number, name, contact, tax, payment terms, credit limit, receivable account, status)
- §0.5.1 organization-scoped uniqueness `UNIQUE (organization_id, customer_number)`
- §0.18 customer schema corrections

**Implementation evidence**

- `app/Models/Customer.php`, `app/Actions/Customer/CreateCustomer.php`,
  `app/Actions/Customer/UpdateCustomer.php`, `app/Http/Controllers/CustomerController.php`,
  `app/Http/Requests/StoreCustomerRequest.php` / `UpdateCustomerRequest.php`
- `app/Policies/CustomerPolicy.php`, composite org-safe FK on receivable account
- Concurrency-safe customer numbering via per-organization sequences (no `MAX+1`)
- `resources/js/Pages/Customers/{Index,CustomerForm}.jsx`, routes `customers.index/store/update`

**Tests:** `tests/Feature/CustomerTest.php`.

---

### 4.2 Customer Invoices (SRS §7.23, §7.24; §0.5.1; §0.7; §0.15; §0.18)

**Status:** Implemented

**SRS / V1.1 references**

- §7.23 Customer Invoices lifecycle (draft → issued → partially paid → paid; void; cancelled)
- §7.24 Invoice Accounting (DR A/R, CR Revenue, CR Tax payable) — posted through §0.7 boundary
- §0.5.1 `UNIQUE (organization_id, invoice_number)` and `UNIQUE (organization_id, idempotency_key)`
- §0.7 `PostJournalEntry` is the single posting boundary
- §0.15 concurrency-safe invoice numbering (no `MAX+1`)
- §0.8 maker-checker: `creator != final poster`

**Implementation evidence**

- `app/Models/CustomerInvoice.php`, `CustomerInvoiceLine.php`
- `app/Actions/CustomerInvoice/{CreateCustomerInvoice,PostCustomerInvoice,VoidCustomerInvoice}.php`
- `PostCustomerInvoice` resolves journal + open fiscal year/period, builds DR A/R / CR revenue / CR
  tax lines, and posts through `PostJournalEntry` inside one transaction; rejects `created_by === poster`.
- `app/Policies/CustomerInvoicePolicy.php` (viewAny/create/post/void), composite org-safe FKs to
  customers, journal entries, accounts, tax codes.
- Concurrency-safe `INV-######` numbering via `organizations.customer_invoice_number_sequence`.
- `resources/js/Pages/Invoices/{Index,CustomerInvoiceForm}.jsx`, routes `invoices.index/store/post/void`.
- Store route behind `password.confirm` (financial-configuration/security scope).

**Tests:** `tests/Feature/CustomerInvoiceTest.php` (lifecycle, maker-checker, closed-period rejection).

---

### 4.3 Customer Payments — Implemented

SRS §7.25, Scenario 4 (DR Cash / CR A/R), §7.24. Status: `voided_at` skipped (SRS does not require
payment voiding); no `void` route.

- `app/Enums/CustomerPaymentStatus.php`; `app/Models/CustomerPayment.php`,
  `CustomerPaymentAllocation.php` (allocation model is schema-only, no domain logic yet);
  `database/factories/CustomerPaymentFactory.php`.
- `app/Actions/CustomerPayment/{CreateCustomerPayment,PostCustomerPayment}.php` —
  concurrency-safe `PAY-######` via `organizations.customer_payment_number_sequence`, idempotency on
  `(organization_id, idempotency_key)`, per-org scoped customer/currency/cash-account checks.
  `PostCustomerPayment` resolves journal + open fiscal year/period, builds DR cash / CR A/R, posts
  through `PostJournalEntry`; rejects `created_by === poster` and missing debit source.
- `app/Policies/CustomerPaymentPolicy.php` (viewAny/create/post), composite org-safe FK on
  `customer_payments.cash_account_id` (2026_09_16_110002) + CHECK
  `ck_customer_payments_debit_source` (posted payments must carry a cash or bank account).
- `app/Http/Controllers/CustomerPaymentController.php`, `StoreCustomerPaymentRequest`,
  routes `payments.index/store/post` (store behind `password.confirm`).
- `database/seeders/PermissionSeeder.php`: `view-payments`, `create-payments`, `approve-payments`.
- `resources/js/Pages/Payments/{Index,CustomerPaymentForm}.jsx`; nav link in `AppLayout` (Ledger group).

**Tests:** `tests/Feature/CustomerPaymentTest.php` (draft lifecycle, numbering, idempotency,
org-scope validation, posting to ledger, maker-checker, closed-period rejection, 404/catch-all).
Suite green: 17 payments + 21 invoices + 17 customer = 55 tests / 159 assertions.

---

## Next eligible item

**Payment Allocation** — the continuation of reconciled Phase 4 (SRS §78 Phase 3 / §0.39 Phase 3;
§7.24; §7.25). Payment drafts can now be created and posted; the open invoices a payment settles are
allocated via `customer_payment_allocations` (§0.9 row-locked allocation, §0.18).

- Schema (`customer_payment_allocations`) is already in place; `CustomerPaymentAllocation` model
  exists but has no domain logic yet.
- Missing: `AllocateCustomerPayment` (row-locked allocation of outstanding invoice balances while
  respecting §0.9), reconcile-on-post so an allocated, fully-settled payment flips invoices to
  partially paid/paid, AR Aging (§7.26) reports can follow once allocation exists.

Date: 2026-09-16