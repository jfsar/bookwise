---
description: Review an implemented Section 7 requirement against the SRS and acceptance criteria
agent: plan
---

Perform a senior-level implementation review for Section 7 requirement `$ARGUMENTS`.

Read:

- `SRS.md`
- `docs/implementation/section-7/$ARGUMENTS.md` if present
- all changed files related to the requirement
- relevant tests

Do not assume that passing tests proves full compliance.

## Review checklist

### Requirement compliance
- Exact SRS behavior is implemented.
- No required behavior is missing.
- No unsupported behavior was invented.

### Architecture
- Code follows the Laravel modular-monolith architecture.
- Domain/application logic is placed in appropriate boundaries.
- Controllers and Livewire components remain orchestration-focused.
- Existing project conventions are reused.

### Data integrity
- Required fields, relationships, constraints, indexes, and transactions are correct.
- Tenant/organization boundaries cannot be bypassed.
- Financial source-of-truth rules are preserved.

### Security
- Authorization is enforced before mutation.
- Input is validated server-side.
- Sensitive data is protected.
- Audit data does not leak secrets.

### Accounting correctness
When applicable:

- Double-entry balance is enforced.
- Journal lines obey debit/credit exclusivity.
- Posted records are immutable.
- Reversals are first-class transactions.
- Closed periods reject prohibited postings.
- Currency/tax snapshots remain historical.
- Derived values are rebuildable.

### Reliability
- Transactions are atomic.
- Idempotency is correct where required.
- Concurrency behavior is safe where required.
- Jobs/events/outbox behavior does not become a hidden source of financial truth.

### Tests
- Tests cover happy paths and negative paths.
- Authorization/security tests exist where relevant.
- Adversarial/concurrency/idempotency tests exist where relevant.
- Tests verify outcomes and invariants, not implementation trivia.

## Review output

Return findings ordered by severity:

- BLOCKER — requirement cannot be considered complete.
- HIGH — important defect or missing acceptance criterion.
- MEDIUM — meaningful correctness, maintainability, security, or test gap.
- LOW — minor improvement.

For every finding include the file and relevant code location, why it matters, and the exact SRS rule it conflicts with.

Then produce:

## Acceptance verdict

Choose exactly one:

- PASS — all applicable goals and acceptance criteria are satisfied.
- PASS WITH NOTES — implementation is compliant, with only non-blocking improvements.
- FAIL — one or more blocking/high-severity gaps remain.

If the verdict is FAIL, do not mark the requirement complete in the work plan.
