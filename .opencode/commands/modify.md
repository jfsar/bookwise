---
description: Modify code or components using an intent-first, agentic workflow
agent: build
---

Modify the code based on $ARGUMENTS.

$ARGUMENTS may describe any requested change, including files, classes, functions, React components, hooks, pages, routes, APIs, UI behavior, UX changes, configuration, or architectural changes.

## Before Editing

### Load Project Skills

- Inspect the available project skills under `.agents/skills/`.
- Identify every skill relevant to the requested change.
- Load and follow each relevant `SKILL.md` before editing.
- Treat loaded skills as project-specific implementation rules.
- Do not load unrelated skills.

## Workflow

### 1. Understand the Intent

- Determine what the requested change should accomplish.
- Inspect the target code and surrounding implementation before editing.
- Identify existing project patterns, reusable components, interfaces, and conventions.
- Preserve existing behavior unless the requested change explicitly requires changing it.
- Do not invent requirements, business rules, UI behavior, or architectural decisions that are not supported by `$ARGUMENTS`, existing code, project documentation, or loaded skills.

### 2. Analyze the Impact

- Search for usages, dependencies, related components, and similar implementations.
- Identify affected frontend, Inertia, Laravel, database, API, authentication/authorization, queue, cache, and test code where applicable.
- Identify contracts or interfaces that must remain compatible.
- Consider accessibility, responsiveness, performance, security, maintainability, scalability, and backward compatibility.

### 3. Plan the Change

- Prefer existing project components, abstractions, and patterns over introducing duplicates.
- Choose the simplest maintainable implementation that satisfies the requested behavior.
- Determine all affected files and layers before editing.
- Avoid unrelated refactoring or unnecessary changes.

### 4. Implement

- Modify all affected code consistently.
- Keep the implementation aligned with the existing architecture and loaded project skills.
- Update related state management, validation, error handling, loading states, accessibility behavior, and integration logic when required by the change.
- Do not modify unrelated functionality.

For example, if asked to "use a modal instead of a normal form", update the relevant component, state management, submission flow, validation, loading/error states, accessibility behavior, and related UI rather than only changing the visual wrapper.

### 5. Test & Verify

- Add or update automated tests whenever the change affects testable behavior.
- Cover relevant normal flows, validation, authorization, error handling, and edge cases.
- Do not add superficial tests solely to satisfy this requirement.
- Run relevant tests, type checks, linting, static analysis, and other project checks.
- Verify existing functionality and check for regressions.

### 6. Review

- Inspect the final diff.
- Remove unnecessary changes, dead code, debugging code, and unrelated modifications.
- Confirm the implementation matches the requested intent.
- Confirm existing architecture, conventions, interfaces, and project skills are respected.

## Completion Criteria

Do not declare completion until:

- The requested behavior is implemented.
- All affected code is updated consistently.
- Relevant tests and project checks pass.
- The change has been verified against the requested intent.
- No unnecessary or unrelated changes remain.
- No known unresolved material issue remains.

## Final Response

Provide a concise implementation report with:

- **Change**
- **Implementation**
- **Tests Updated**
- **Verification**
- **Related Changes / Risks**
