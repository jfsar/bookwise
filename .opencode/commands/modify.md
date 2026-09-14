---
description: Modify code or components using an intent-first, agentic workflow
agent: build
---

Modify the code based on $ARGUMENTS.

$ARGUMENTS may describe any requested change, including files, classes, functions, React components, hooks, pages, routes, APIs, UI behavior, UX changes, or architectural changes.

Follow this workflow:

1. **Understand the Intent**
   - Determine what the requested change should accomplish.
   - Inspect the target code and surrounding implementation before editing.
   - Identify existing project patterns, reusable components, and conventions.

2. **Analyze the Impact**
   - Search for usages, dependencies, related components, and similar implementations.
   - Identify affected frontend, Inertia, Laravel, database, API, and test code where applicable.
   - Consider accessibility, responsiveness, performance, security, maintainability, and backward compatibility.

3. **Plan the Change**
   - Prefer existing project components and patterns over introducing duplicates.
   - Choose the cleanest implementation that satisfies the requested behavior.
   - Avoid unrelated refactoring or unnecessary changes.

4. **Implement**
   - Modify all affected code consistently.
   - Example: if asked to "use a modal instead of a normal form", update the relevant component, state management, submission flow, validation, loading/error states, accessibility behavior, and related UI rather than only changing the visual wrapper.

5. **Test & Verify**
   - Always add or update automated tests for behavior affected by the change.
   - Run relevant tests, type checks, linting, and other project checks.
   - Verify existing functionality and check for regressions.

6. **Review**
   - Inspect the final diff.
   - Remove unnecessary changes.
   - Confirm the implementation matches the requested intent and existing architecture.

Do not declare completion until the requested behavior is implemented, affected tests/checks pass, and the change has been verified.

Final response:
- **Change**
- **Implementation**
- **Tests Updated**
- **Verification**
- **Related Changes / Risks**