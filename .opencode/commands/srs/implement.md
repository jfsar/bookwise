---
description: Implement a planned Section 7 SRS requirement with production-quality code
agent: build
---

Implement Section 7 requirement `$ARGUMENTS` from `SRS.md` using the focused work plan:

`docs/implementation/section-7/$ARGUMENTS.md`

## Before coding

Read the work plan and re-check the authoritative SRS. Inspect the existing implementation so you do not duplicate existing abstractions.

SRS.md is the source of truth. Version 1.1 corrections and invariants take precedence over conflicting older requirements.

## Implementation rules

- Implement only the planned requirement and necessary supporting changes.
- Follow existing Laravel modular-monolith conventions.
- Reuse existing domain actions/services/components where appropriate.
- Keep business rules out of controllers and Livewire components when a domain/application boundary is appropriate.
- Enforce authorization before mutation.
- Enforce tenant/organization ownership at application and database boundaries where required.
- Use database transactions for atomic financial mutations.
- Never make posted financial facts mutable merely to simplify a workflow.
- Respect lifecycle transitions and maker-checker rules.
- Implement idempotency and row-locking behavior where required.
- Protect sensitive information.
- Avoid unrelated refactors.

## Definition of done for this command

The feature is not done until:

1. Required production code is implemented.
2. Required migrations/schema constraints are implemented.
3. Required policies/authorization are implemented.
4. Required UI behavior is implemented, when applicable.
5. Automated tests are created or updated.
6. Focused tests pass.
7. The work plan is updated with implementation notes and current status.

Run relevant tests while implementing and fix failures rather than suppressing them.

At the end, summarize the implementation and list any work intentionally left for the test/review phases.
