---
description: Debug and fix issues by finding the root cause, adding tests, and verifying the fix
agent: build
---

Debug and fix the issue described by $ARGUMENTS.

Treat $ARGUMENTS as unrestricted context. It may contain errors, stack traces, files, classes, methods, functions, React components, hooks, routes, endpoints, queries, or expected/actual behavior.

Before implementing changes:

- Discover the available project skills under .agents/skills/.
- Identify and load every skill relevant to the task.
- Treat loaded skills as project-specific instructions and follow them throughout the implementation.
- Do not ignore a relevant skill merely because the requested change appears simple.

Follow this workflow:

1. Inspect the repository and relevant code.
2. Reproduce the issue when possible.
3. Trace the complete execution/data flow across React → Inertia → Laravel → database/services as applicable.
4. Search for related usages and similar problems.
5. Form and validate hypotheses using evidence.
6. Identify the root cause before modifying code. Do not patch symptoms or make speculative changes.
7. Implement the smallest clean fix consistent with the existing architecture.
8. Consider security, performance, data integrity, and regression risks.
9. Always add or update automated tests covering the issue.
10. Run the relevant tests and checks, fixing failures caused by the change.
11. Reproduce/verify the original scenario and review the final diff for unnecessary changes.

Do not declare completion unless the root cause is fixed, tests covering the issue pass, relevant checks pass, and the original problem is verified as resolved.

Final response:

- Root Cause
- Fix
- Tests Added/Updated
- Verification
- Related Issues/Risks
