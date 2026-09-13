---
description: Create and execute automated tests for a Section 7 SRS requirement
agent: build
---

Create and execute the automated test strategy for Section 7 requirement `$ARGUMENTS` from `SRS.md`.

Read:

- `SRS.md`
- `docs/implementation/section-7/$ARGUMENTS.md` if present
- the current implementation related to the requirement
- existing tests and project testing conventions

## Test design

Test the behavior required by the SRS, not merely implementation details.

Cover the applicable categories:

### Happy path
- Valid inputs.
- Expected state transitions.
- Expected persisted records.
- Expected UI/application result.

### Validation and negative cases
- Missing/invalid input.
- Invalid monetary values.
- Invalid lifecycle transitions.
- Missing required relationships.
- Duplicate operations.
- Other failure cases explicitly required by the SRS.

### Authorization and security
- Allowed roles can perform allowed actions.
- Forbidden roles/actions are denied.
- Cross-organization access is denied.
- Sensitive data is protected where applicable.

### Accounting integrity
When applicable, verify:

- Debits equal credits.
- Journal line exclusivity.
- Posting-account validity.
- Period membership.
- Currency/rate snapshot behavior.
- Financial immutability.
- Reversal rules.
- Audit trail creation.
- Derived values remain consistent with authoritative records.

### Concurrency/idempotency
When applicable, verify:

- Duplicate retries do not create duplicate accounting facts.
- Allocation cannot overrun payment/invoice/bill balances.
- Concurrent operations are safely serialized or rejected according to the SRS.
- Closing/posting races are handled safely.

### Regression
Run relevant existing tests that could be affected by the change.

## Execution

Run the narrowest useful test command first, then the broader suite required for confidence. Do not weaken assertions just to make tests pass.

If a failure exposes an implementation bug, fix the production code and rerun the test. If it exposes a test problem, fix the test without hiding the requirement.

Update `docs/implementation/section-7/$ARGUMENTS.md` with:

- Test cases added.
- Commands executed.
- Results.
- Any unresolved failure or blocker.
