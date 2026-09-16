---
description: Debug and fix issues by finding the root cause, adding regression tests, and verifying the fix
agent: build
---

Debug and fix the issue described by $ARGUMENTS.

Treat $ARGUMENTS as unrestricted debugging context. It may contain errors, stack traces, files, classes, methods, functions, React components, hooks, routes, endpoints, queries, logs, configuration, or expected/actual behavior.

## Before Implementing Changes

### Load Project Skills

- Inspect the available project skills under `.agents/skills/`.
- Identify every skill relevant to the debugging task.
- Load and follow each relevant `SKILL.md`.
- Treat loaded skills as project-specific instructions throughout the investigation and implementation.
- Do not ignore a relevant skill merely because the issue appears simple.

## Debugging Workflow

### 1. Inspect

- Inspect the repository structure and relevant project documentation.
- Inspect the affected code and surrounding implementation.
- Identify relevant frontend, Inertia, Laravel, database, API, authentication/authorization, queue, cache, and external-service layers as applicable.

### 2. Reproduce

- Reproduce the issue when possible.
- Capture the actual failure, affected inputs/state, and relevant execution context.
- If reproduction is not possible, use available logs, stack traces, failing tests, code paths, configuration, and other evidence.
- Do not claim that an issue was reproduced when it was not.

### 3. Trace

- Trace the complete execution and data flow across affected layers.
- Search for related usages, dependencies, similar implementations, recent changes, and existing workarounds.
- Inspect validation, state transitions, database queries, relationships, transactions, external integrations, and error handling as applicable.

### 4. Diagnose

- Form hypotheses based on evidence.
- Validate or eliminate each relevant hypothesis using code inspection, tests, logs, reproduction, or other available evidence.
- Identify the root cause before modifying code.
- Do not patch symptoms or make speculative changes.
- Clearly distinguish confirmed causes from remaining uncertainty.

### 5. Fix

- Implement the smallest clean fix that addresses the root cause.
- Follow the existing architecture, conventions, and loaded project skills.
- Preserve existing behavior outside the affected scenario.
- Consider security, performance, data integrity, concurrency, reliability, and backward compatibility as applicable.
- Do not perform unrelated refactoring.

For database-related issues:

- Inspect queries, relationships, constraints, transactions, locking/concurrency, migrations, and data consistency as applicable.
- Do not modify or destroy existing data merely to reproduce or test an issue unless explicitly required and safely isolated.

### 6. Regression Testing

- Add or update automated tests covering the issue.
- Prefer a regression test that fails before the fix and passes after the fix whenever practical.
- Cover relevant edge cases and affected behavior.
- Update existing tests when the expected behavior legitimately changes.
- Do not add superficial tests solely to satisfy this requirement.

### 7. Verify

- Run the relevant automated tests.
- Run applicable type checks, linting, static analysis, and other project checks.
- Reproduce or otherwise verify the original failure scenario again.
- Confirm the root cause is addressed rather than merely hidden.
- Check for regressions in related functionality.

### 8. Review

- Inspect the final diff.
- Remove unnecessary changes.
- Remove temporary debugging code, instrumentation, bypasses, fixtures, and diagnostic artifacts unless intentionally required by the final solution.
- Confirm the implementation remains consistent with the existing architecture and project skills.

## Completion Criteria

Do not declare completion unless:

- The root cause has been identified using evidence.
- The fix addresses the root cause.
- Regression tests covering the issue pass.
- Relevant project checks pass.
- The original problem has been reproduced and verified as resolved, or sufficient alternative evidence has verified the resolution when reproduction is impossible.
- No unnecessary or unrelated changes remain.
- No known unresolved material issue remains.

## Final Response

Provide a concise debugging report with:

- **Root Cause**
- **Fix**
- **Tests Added/Updated**
- **Verification**
- **Related Issues/Risks**
