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
| 4 — Accounts Receivable | not_started |
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

## Next eligible item

With Phase 3 complete, the reconciled roadmap moves to **Phase 4 — Accounts Receivable**
(SRS §0.39 Phase 3 / §78 Phase 3): Customers, Invoices, Customer Payments, Payment Allocation
(row-locked, idempotent, over-allocation-safe), Credit Notes, and AR Aging, all posting through
the single `PostJournalEntry` boundary.