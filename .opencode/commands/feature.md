---
description: Implement a new feature using a requirements-to-verification workflow
agent: build
---

Implement the new feature described by $ARGUMENTS.

$ARGUMENTS may contain feature requests, functional requirements, non-functional requirements, business rules, UI/UX requirements, API behavior, database changes, or any combination of them.

## Before Implementation

### Load Project Skills

- Inspect the project's available skills under `.agents/skills/`.
- Identify every skill relevant to the requested feature.
- Load and follow each relevant `SKILL.md`.
- Treat loaded skills as authoritative project-specific implementation guidance.
- Do not load unrelated skills.

### Resolve Requirements

- Determine the feature's intended behavior and scope.
- Separate functional and non-functional requirements.
- Identify constraints, dependencies, acceptance criteria, affected systems, and integration points.
- Do not invent business rules, architecture decisions, UI behavior, or requirements.
- If a material ambiguity or contradiction cannot be resolved from the repository, project documentation, or loaded skills, ask for clarification before implementation.

## Workflow

### 1. Inspect the Existing System

- Explore the repository structure, architecture, conventions, and reusable components.
- Search for related features, patterns, services, models, APIs, and tests.
- Identify affected React, Inertia, Laravel, database, API, authentication/authorization, queues, cache, and external-service components as applicable.
- Check existing tests and project-specific development commands.

### 2. Design Before Implementing

- Choose the simplest maintainable design consistent with the existing architecture.
- Reuse existing patterns and components where appropriate.
- Define required data, validation, business rules, states, error handling, and integrations.
- Consider security, performance, accessibility, scalability, reliability, observability, and backward compatibility.
- Avoid introducing unnecessary abstractions or dependencies.

### 3. Implement

- Implement the feature end-to-end across all required layers.
- Update migrations, models, services, controllers, form requests, policies, routes, React components, Inertia pages, APIs, configuration, and other affected components as necessary.
- Follow existing project conventions and loaded skills.
- Avoid unrelated refactoring.

### 4. Test

- Add or update automated tests for the new functionality.
- Cover:
    - normal flows
    - validation
    - authorization
    - error handling
    - important edge cases
    - relevant integration behavior
- Run the most relevant automated tests.
- Run applicable type checks, linters, static analysis, and other project checks.

### 5. Verify & Review

- Verify the implementation against every identified functional requirement.
- Verify relevant non-functional requirements.
- Check affected integration points.
- Check for regressions in existing functionality.
- Review the final diff.
- Remove unnecessary changes, dead code, debugging code, and unrelated modifications.
- Confirm migrations, configuration, routes, tests, and documentation are consistent.

## Completion Criteria

Do not declare completion until:

- The feature is implemented end-to-end.
- All identified requirements are satisfied.
- Relevant automated tests pass.
- Relevant project checks pass.
- The implementation has been reviewed against the requirements.
- No known unresolved material issue remains.
- The final diff contains no unnecessary changes.

## Final Response

Provide a concise implementation report with:

- **Feature Implemented**
- **Functional Requirements**
- **Non-Functional Considerations**
- **Implementation**
- **Tests Added/Updated**
- **Verification**
- **Related Changes / Risks**
