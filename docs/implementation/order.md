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
| 6 — Banking | complete |
| 7 — Enterprise & Operational Features | in_progress |
| 8 — Reporting Optimization & Derived Performance | not_started |
| 9 — Reliability & Production Hardening | not_started |

## Phase 7 — Enterprise & Operational Features (§7.39 Notifications) — Status: next

Scope: in-app notifications with per-user read state, and event-driven creation from journal, approval, and payment activity.

- **Next:** Model, migration, and policy for notifications, then the creation listener/action and the Inertia notification center.

## Next

Phase 7 item 2 of 4: §7.39 Notifications, then §7.38 Attachments, and advanced permissions.
