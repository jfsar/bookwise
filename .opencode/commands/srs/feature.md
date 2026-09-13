---
description: Execute the complete SRS requirement workflow for a specific Section 7 requirement
agent: build
---

You are implementing one specific requirement from the project's `SRS.md`.

Target requirement: `$ARGUMENTS`

Your job is to execute the complete requirement workflow in order:

1. READ AND UNDERSTAND
2. PLAN AND DEFINE GOALS
3. INSPECT THE EXISTING CODEBASE
4. IMPLEMENT
5. CREATE/UPDATE AUTOMATED TESTS
6. RUN VERIFICATION
7. REVIEW AGAINST THE SRS
8. FIX GAPS
9. REPORT COMPLETION

## 1. Source of truth

Treat `SRS.md` as the authoritative functional specification.

The target will normally be a Section 7 identifier such as `7.5`, `7.14`, or a more specific identifier if the SRS contains one. Resolve the target exactly from `SRS.md`; do not invent requirements.

When reading the target requirement, also inspect relevant cross-cutting requirements in `SRS.md`, especially:

- Version 1.1 architecture corrections and precedence rules.
- Architectural principles and accounting invariants.
- Database schema and relationship definitions relevant to the feature.
- Business rules.
- Non-functional requirements relevant to the feature.
- Testing requirements.
- Acceptance criteria.
- The recommended implementation order.

Version 1.1 takes precedence wherever it conflicts with an older requirement.

The application is a Laravel modular monolith using Blade + Livewire, PHP/Laravel, PostgreSQL, and Redis. Preserve the architecture already established by the repository and SRS.

## 2. Requirement understanding

Before changing code, determine:

- Exact target requirement title and text.
- Parent Section 7 feature and nearby requirements that affect it.
- Functional behavior required.
- Validation rules.
- Authorization/policy requirements.
- Database entities, fields, indexes, constraints, and relationships involved.
- Domain actions/services required.
- State transitions and invariants.
- Audit requirements.
- Idempotency/concurrency requirements, when applicable.
- UI requirements, when applicable.
- Notifications/jobs/events, when applicable.
- Required automated tests.
- Acceptance criteria that prove completion.

If the SRS is ambiguous, identify the ambiguity and choose the smallest implementation consistent with the explicit requirements and existing architecture. Do not silently invent business behavior.

## 3. Inspect the repository

Inspect the current codebase before planning implementation. Identify existing:

- migrations
- models and relationships
- policies/authorization
- actions/commands/domain services
- requests/rules/DTOs
- Livewire components and Blade views
- routes
- jobs/events/listeners/notifications
- factories/seeders
- tests
- existing conventions and reusable components

Prefer extending existing patterns over introducing parallel architectures.

## 4. Create an implementation plan

Create or update a focused work plan at:

`docs/implementation/section-7/$ARGUMENTS.md`

The plan must contain:

- Requirement reference and title.
- SRS references used.
- Current-state findings.
- Goals.
- Non-goals.
- Implementation steps in dependency order.
- Files/components likely to change.
- Database changes.
- Domain/application changes.
- UI changes.
- Authorization/security changes.
- Tests to add or update.
- Verification commands.
- Acceptance checklist.
- Risks/ambiguities and how they were handled.

Do not treat the plan as permission to broaden scope.

## 5. Implement

Implement the requirement end-to-end.

Rules:

- Follow repository conventions.
- Keep controllers and Livewire components orchestration-focused.
- Put business rules in the appropriate action/domain/service layer.
- Enforce authorization before mutation.
- Use database transactions for atomic financial operations.
- Preserve tenant/organization isolation.
- Preserve financial immutability and ledger source-of-truth rules.
- Use idempotency where the SRS requires retriable operations.
- Handle concurrency explicitly where the SRS requires row locking or race protection.
- Do not introduce API endpoints when the SRS says no API is required.
- Do not implement optional future functionality unless the requirement explicitly makes it necessary.
- Do not refactor unrelated code just for style.

## 6. Tests

For every implemented behavior, create or update automated tests at the appropriate level:

- Unit tests for pure/domain calculations and invariants.
- Feature tests for application workflows and authorization.
- Database tests for constraints and relationships where appropriate.
- Concurrency/adversarial tests for race-sensitive accounting behavior.
- Regression tests for existing behavior that could be affected.

Negative cases are mandatory, not optional. Test unauthorized access, invalid state transitions, invalid monetary data, tenant isolation, and duplicate/retry behavior whenever relevant to the target requirement.

Run the narrowest relevant tests first, then the broader test suite needed for confidence.

## 7. Review and self-correct

After implementation and tests, perform a fresh review as if you were a senior reviewer who did not write the code.

Compare the resulting behavior against the exact SRS requirement and all relevant V1.1 rules.

Check:

- Functional completeness.
- Database correctness.
- Authorization.
- Validation.
- Security.
- Financial invariants.
- Tenant isolation.
- Lifecycle correctness.
- Concurrency/idempotency.
- Auditability.
- Test coverage.
- Regression risk.
- Code maintainability.
- UI usability/accessibility where applicable.

For every missed goal or failed test, fix the implementation and rerun the affected verification. Repeat until the acceptance checklist is satisfied or a real blocker prevents completion.

## 8. Completion gate

Do not claim the requirement is complete merely because code exists.

Mark it complete only when:

- The implementation matches the target SRS requirement.
- Required supporting requirements were honored.
- Required tests exist.
- Relevant tests pass.
- No known acceptance criterion is intentionally skipped.
- The work plan's acceptance checklist is updated.

At the end, report:

1. Requirement implemented.
2. Goals completed.
3. Files changed.
4. Tests added/updated.
5. Verification commands and results.
6. Any remaining limitation or explicit blocker.
