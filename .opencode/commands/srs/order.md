---
description: Determine and execute the next implementation step by combining SRS.md §0.39 V1.1 implementation order with §78 Recommended MVP
agent: build
---

# SRS Implementation Order

Use `SRS.md` as the authoritative specification.

This command defines the implementation roadmap by **comparing and reconciling**:

- **SRS.md §0.39 — Recommended V1.1 implementation order**: the authoritative production-oriented dependency and hardening sequence.
- **SRS.md §78 — Recommended MVP**: the incremental feature/value sequence for getting the core product working.

When the two sequences differ, **do not blindly copy either list**. Use the following decision rule:

1. V1.1 architectural, financial-integrity, security, concurrency, and database-hardening requirements take precedence over MVP convenience.
2. MVP feature order is used to organize user-visible business functionality inside those safe technical boundaries.
3. A prerequisite may be pulled earlier when a later MVP feature cannot be implemented safely without it.
4. A feature may be postponed when it is not required for the MVP path and introducing it early would add unnecessary complexity.
5. Never weaken a V1.1 invariant merely to reach an MVP milestone faster.

## Objective

Determine the **next eligible implementation item**, explain why it is next, execute it when requested, create/update tests, review it against the SRS, and keep a durable progress record.

---

# 1. Reconciled Implementation Roadmap

## Phase 0 — Foundation & Schema Hardening

**Source:** §0.39 Phase 0, strengthened by V1.1 database and architecture rules.

Work items:

- Laravel project/application foundation
- organization/tenant-scoped schema foundations
- tenant-safe composite keys and foreign keys
- database constraints and indexes needed by the current phase
- concurrency-safe document numbering
- lifecycle/state definitions
- migration conventions and seed/test foundations
- base authorization boundary needed before protected mutations

**Exit criteria:**

- Database foundations required by subsequent work are implemented.
- Tenant isolation is enforced wherever V1.1 requires database enforcement.
- Core lifecycle and numbering rules are represented in the schema/domain.
- Foundational tests pass.

Do **not** treat this phase as optional just because the MVP list starts with business entities. V1.1 makes these controls implementation prerequisites.

---

## Phase 1 — MVP Accounting Core

**Source:** §78 Phase 1, aligned with §0.39 Phase 1.

MVP entities/features:

- Organizations
- Users
- Roles
- Permissions
- Account Types
- Accounts
- Fiscal Years
- Accounting Periods
- Journals
- Journal Entries
- Journal Entry Lines

V1.1 accounting-core controls that must be included here:

- journal balance invariant
- journal line debit/credit exclusivity
- posting-account validation
- period membership validation
- currency consistency
- posted financial immutability
- reversal model foundations
- transactional posting boundary
- authorization before mutation
- audit event at required financial transitions
- idempotency strategy for retriable posting operations

**Exit criteria:**

A valid journal can be created, validated, approved where applicable, posted transactionally, and represented as authoritative ledger data without violating V1.1 accounting invariants.

---

## Phase 2 — Ledger & Core Reporting

**Source:** §78 Phase 2.

Work items:

- General Ledger
- Trial Balance
- Income Statement
- Balance Sheet
- Cash Flow

Rules:

- Reports derive from posted journal data.
- Posted journal data remains the financial source of truth.
- Derived totals must have a deterministic rebuild/reconciliation path.
- Report drill-down should lead back to authoritative journal lines.

**Exit criteria:**

The core financial statements and ledger reconcile to posted journal data, including the accounting invariants defined by V1.1.

---

## Phase 3 — Approval & Security Controls

**Source:** §0.39 Phase 2, intentionally placed before AR/AP because approval and authorization protect financial mutations.

Work items:

- maker-checker controls
- approval workflows and approval requests
- authorization and policy enforcement
- MFA/re-authentication hooks where applicable
- audit-log integrity/tamper evidence
- security hardening around privileged accounting operations

Why this differs from §78:

§78 places approval/security under Enterprise Features after banking, while §0.39 places it before AR/AP. For this implementation command, **§0.39 wins** because invoices, bills, payments, and allocations are financial mutations that must not bypass approval, authorization, or audit controls where policy requires them.

**Exit criteria:**

Protected financial operations enforce authorization and maker-checker policy where configured, and audit records satisfy the V1.1 requirements.

---

## Phase 4 — Accounts Receivable

**Source:** §78 Phase 3, aligned with §0.39 Phase 3.

Work items:

- Customers
- Invoices
- Customer Payments
- Payment Allocation
- Credit Notes
- AR Aging

V1.1 controls:

- tenant-safe customer/invoice/payment relationships
- transactional posting through the accounting boundary
- immutable posted financial values
- idempotent posting
- row locking for payment allocation
- payment over-allocation prevention
- invoice outstanding-balance protection
- audit trail

**Exit criteria:**

AR transactions create correct accounting entries and cannot violate allocation, tenant, immutability, or concurrency guarantees.

---

## Phase 5 — Accounts Payable

**Source:** §78 Phase 4, aligned with §0.39 Phase 3.

Work items:

- Vendors
- Bills
- Vendor Payments
- Payment Allocation
- Debit Notes
- AP Aging

Apply the same V1.1 financial, authorization, idempotency, tenant, immutability, and allocation-concurrency safeguards used by AR.

**Exit criteria:**

AP transactions post correctly and allocation concurrency is safe under parallel operations.

---

## Phase 6 — Banking

**Source:** §78 Phase 5, aligned with §0.39 Phase 4.

Work items:

- Bank Accounts
- Bank Transactions
- Import processing
- Deduplication/idempotent retry
- Many-to-many reconciliation
- Reconciliation finalization/correction controls

V1.1 controls:

- sensitive bank-account protection
- tenant-safe relationships
- import identity/fingerprint strategy
- many-to-many reconciliation model
- immutable finalized reconciliation except controlled correction/reopen
- concurrency/idempotency safeguards

**Exit criteria:**

Bank imports and reconciliation are repeatable, deduplicated, tenant-safe, and consistent with authoritative accounting data.

---

## Phase 7 — Enterprise & Operational Features

**Source:** §78 Phase 6, with the pieces already moved earlier by V1.1 dependencies excluded from this phase.

Work items:

- Recurring Journals
- Notifications
- Attachments
- Advanced Permissions refinements

Notes:

- Recurring journals must respect approval requirements and idempotent scheduled execution.
- Notifications must not be required for financial correctness.
- Attachments require private storage, authorization, validation, and scanning controls.
- Advanced permissions refine the authorization model rather than replacing existing policy enforcement.

**Exit criteria:**

These features operate without becoming competing financial sources of truth or bypassing existing controls.

---

## Phase 8 — Reporting Optimization & Derived Performance

**Source:** §0.39 Phase 5.

Work items:

- report services/query isolation
- drill-down optimization
- asynchronous large exports
- rebuildable derived balance structures
- dashboard aggregate optimization

Rules:

- Reporting remains ledger-derived.
- Large exports are asynchronous.
- Derived data can always be rebuilt from authoritative data.
- Performance optimization must not change financial truth.

**Exit criteria:**

Reporting scales without introducing competing accounting state or blocking interactive requests with unbounded large-report work.

---

## Phase 9 — Reliability & Production Hardening

**Source:** §0.39 Phase 6.

Work items:

- outbox events
- generic/operation-specific idempotency infrastructure
- observability and correlation IDs
- queue reliability and retry handling
- backup/restore verification
- recovery/reprocessing tests
- production-oriented monitoring

**Exit criteria:**

Critical retriable operations are idempotent, durable events are reliable, operational failures are diagnosable, and backup/restore procedures are tested.

---

# 2. How to Choose the Next Item

When `/srs/order` runs:

1. Read `SRS.md` and inspect the relevant parts of:
   - §0.39 Recommended V1.1 implementation order
   - §78 Recommended MVP
   - Section 7 Functional Requirements
   - Section 8 Non-Functional Requirements
   - Section 9 Business Rules
   - V1.1 invariants
   - V1.1 acceptance criteria
   - V1.1 implementation checklist
2. Inspect the repository rather than assuming the project is empty or complete.
3. Determine the status of every roadmap phase and work item:
   - `not_started`
   - `in_progress`
   - `blocked`
   - `complete`
4. Find the earliest incomplete **eligible** item in the reconciled roadmap.
5. A later item may be selected only when:
   - all required earlier prerequisites are complete, or
   - the later item is explicitly independent and implementing it does not bypass an incomplete safety prerequisite.
6. Prefer small, testable vertical slices when several items are equally eligible.
7. Never skip a security, integrity, concurrency, or database prerequisite simply because a UI feature would be quicker to demonstrate.

---

# 3. Mapping Work to Section 7

For each selected item, identify the exact related Section 7 requirement(s), for example:

```text
Phase 1 / Accounts
    -> §7.5 Chart of Accounts

Phase 1 / Fiscal Years
    -> §7.6 Fiscal Years

Phase 1 / Journals
    -> §7.10 Journal Entries
    -> §7.11 Journal Entry Lines
    -> §7.12 Manual Journals
    -> §7.14 Journal Posting
    -> §7.15 Journal Reversal

Phase 2 / Ledger
    -> §7.17 General Ledger
    -> §7.18 Trial Balance
    -> §7.19 Income Statement
    -> §7.20 Balance Sheet
    -> §7.21 Cash Flow Statement
```

Also map the item to applicable V1.1 invariants, database constraints, business rules, and acceptance criteria.

---

# 4. Execution Workflow

If the command is used to execute work, perform this lifecycle:

```text
Read requirement
      ↓
Inspect existing implementation
      ↓
Identify prerequisites
      ↓
Create implementation plan + goals
      ↓
Implement
      ↓
Create/update automated tests
      ↓
Run relevant tests
      ↓
Review against SRS
      ↓
Fix failures/gaps
      ↓
Re-run tests
      ↓
Record completion/evidence
```

Before declaring completion, verify:

- functional requirement satisfied
- applicable V1.1 invariants satisfied
- authorization enforced
- tenant isolation enforced where applicable
- database constraints present where required
- appropriate unit/feature/security/concurrency/property tests exist
- relevant tests pass
- no prohibited shortcuts were introduced

---

# 5. Durable Progress Record

Maintain:

`docs/implementation/order.md`

For every run, record:

- roadmap phase
- work item
- status
- related SRS §7 references
- related V1.1 references
- implementation evidence
- tests added/updated
- tests executed and results
- blockers
- next eligible item
- date/status update

Do not mark a phase complete merely because its files, migrations, or UI exist. Completion means the feature works, is appropriately tested, and satisfies the relevant SRS requirements.

---

# 6. Required Output

At the end of each run, report:

1. **Current phase**
2. **Current work item**
3. **Why it is next**
4. **MVP vs V1.1 ordering decision**, when the two roadmaps differ
5. **Relevant SRS references**
6. **Implementation status**
7. **Test status**
8. **Blockers or unresolved gaps**
9. **Next eligible work item**
10. Confirmation that `docs/implementation/order.md` was updated

If execution was requested and completed, state the next eligible item rather than continuing into unrelated later phases.
