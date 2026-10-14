# Implementation Order & Progress

Tracks SRS.md execution. See SRS.md §0.39 for the authoritative order.

## Phase Status

| Phase                                            | Status      |
| ------------------------------------------------ | ----------- |
| 0 — Foundation & Schema Hardening                | complete    |
| 1 — MVP Accounting Core                          | complete    |
| 2 — Ledger & Core Reporting                      | complete    |
| 3 — Approval & Security Controls                 | complete    |
| 4 — Accounts Receivable                          | complete    |
| 5 — Accounts Payable                             | complete    |
| 6 — Banking                                      | complete    |
| 7 — Enterprise & Operational Features            | complete    |
| 8 — Reporting Optimization & Derived Performance | complete    |
| 9 — Reliability & Production Hardening           | not_started |

## Next

Work item: Phase 9 — durable outbox event publication and generic idempotency infrastructure.
Rationale: `outbox_events` and `idempotency_keys` tables exist (§0.25, §0.26) but no publisher/worker or shared idempotency store is implemented; financial lifecycle events currently use after-commit dispatch only.

## Phase 8 (completed)

Scope: rebuildable derived balance structures + reconciliation command (§0.23, §0.39 Phase 5).

- Files: `app/Services/DerivedBalanceService.php`, `app/Console/Commands/RebuildDerivedBalances.php`, models `ReportingFactTable`, `AccountDailyBalance`, `LedgerBalanceSnapshot`, three migrations.
- Command: `accounting:rebuild-derived-balances [--organization=] [--check]` — rebuilds derived rows from posted journal data or verifies them without writing.
- Tests: `tests/Feature/DerivedBalanceRebuildTest.php` (11 passing).

### Integration status (deliberate)

Reports and the dashboard continue to read posted journal data directly
(`app/Reports/ReportQuery.php`, `app/Dashboard/DashboardMetricsService.php`),
per the SRS "Authoritative reporting path" (§0.23): reports must use posted
accounting data unless a report explicitly says otherwise. The derived tables
(`reporting_fact_tables`, `account_daily_balances`, `ledger_balance_snapshots`)
are the "larger datasets" performance layer (§0.23 Performance strategy) and
are **verified groundwork only** — nothing consumes them yet.

This is intentional so that reporting cannot read stale or drifting derived
state. Wire a report or the dashboard to these structures only when a dataset
is large enough to need it, and only behind a refresh path that keeps them
current (see below).

### Refresh & reconciliation

- `DerivedBalanceService` derives every structure in a single chunked pass over
  posted journal lines, using exact `bcmath` decimal arithmetic (never floats),
  so monetary values and drift detection stay exact.
- `rebuild()` replaces the organization's derived rows inside one transaction
  (posted facts are never modified); the structures are deterministic and
  idempotent.
- `verify()` is read-only and memory-bounded, reporting missing, mismatched, and
  extra rows per table.
- `accounting:rebuild-derived-balances` runs nightly (see `bootstrap/app.php`) to
  keep the structures fresh and reproducible. Run
  `accounting:rebuild-derived-balances --check` (returns non-zero on drift) as
  the reconciliation/alerting entry point.
