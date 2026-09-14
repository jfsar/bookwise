---
description: Add new features or functionality using an agentic requirements-to-verification workflow
agent: build
---

Implement the new feature described by $ARGUMENTS.

$ARGUMENTS may contain a feature request, functional requirements, non-functional requirements, business rules, UI/UX requirements, API behavior, database changes, or any combination of them.

Follow this workflow:

1. **Understand Requirements**
   - Determine the feature's intended behavior and scope.
   - Separate **functional** and **non-functional** requirements.
   - Identify ambiguities, constraints, dependencies, and acceptance criteria from the request and existing project behavior.

2. **Inspect the Existing System**
   - Explore the repository, architecture, conventions, and reusable components.
   - Search for related features, patterns, services, models, APIs, and tests.
   - Identify affected **React, Inertia, Laravel, database, API, authentication/authorization, queues, cache, and external services** as applicable.

3. **Design Before Implementing**
   - Choose the simplest maintainable design consistent with the existing architecture.
   - Reuse existing patterns and components where appropriate.
   - Define required data, validation, business rules, states, error handling, and integrations.
   - Consider security, performance, accessibility, scalability, reliability, observability, and backward compatibility.

4. **Implement**
   - Implement all required layers consistently.
   - Update migrations, models, services, controllers, requests, policies, routes, React components, Inertia pages, APIs, and configuration where necessary.
   - Avoid unrelated refactoring.

5. **Test**
   - Always add or update automated tests for the new functionality.
   - Cover normal flows, validation, authorization, error cases, and important edge cases.
   - Run relevant tests, type checks, linting, and other project checks.

6. **Verify & Review**
   - Verify the feature against its functional and non-functional requirements.
   - Check integration points and existing functionality for regressions.
   - Review the final diff and remove unnecessary changes.

Do not declare completion until the feature is implemented end-to-end, requirements are satisfied, automated tests and relevant checks pass, and the feature has been verified.

Final response:
- **Feature Implemented**
- **Functional Requirements**
- **Non-Functional Considerations**
- **Implementation**
- **Tests Added/Updated**
- **Verification**
- **Related Changes / Risks**