---
description: Perform an agentic QA and code review focused on quality, security, scalability, and UX
agent: plan
---

Review the code described by $ARGUMENTS.

$ARGUMENTS may contain files, directories, classes, functions, React components, pages, APIs, routes, features, or specific review concerns.

Before reviewing:

- Discover the available project skills under .agents/skills/.
- Identify and load every skill relevant to the code being reviewed.
- Use those skills as project-specific review criteria.

Perform a thorough review before making recommendations.

## Review Areas

Evaluate the relevant implementation for:

- **Correctness & QA** — bugs, edge cases, error handling, regressions, test coverage
- **Project Conventions** — Laravel, Inertia, React, naming, structure, patterns, consistency
- **Architecture & SOLID** — separation of concerns, coupling, cohesion, duplication, maintainability
- **Scalability & Performance** — database queries, N+1 issues, caching, rendering, network requests, queues, resource usage
- **Security** — authentication, authorization, validation, injection, XSS, CSRF, mass assignment, data exposure, secrets, rate limiting
- **UI/UX** — usability, consistency, responsive behavior, loading/error/empty states, accessibility, keyboard navigation, feedback
- **Reliability & Observability** — failure handling, logging, idempotency, monitoring, recoverability
- **Testing** — unit, feature/integration, frontend, edge-case, and regression coverage

Inspect related code and usages when necessary to validate findings. Do not report speculative issues without sufficient evidence.

## Severity

Classify every finding as:

**Critical** — security vulnerability, data loss/corruption, severe production failure, or system-breaking issue.

**High** — significant correctness, security, performance, scalability, or maintainability problem likely to cause real impact.

**Medium** — meaningful defect, architectural weakness, UX/accessibility problem, or missing test that should be addressed.

**Low** — minor convention, readability, consistency, or non-critical improvement.

Report findings from highest to lowest severity.

For each finding include:

- **Severity**
- **Location**
- **Issue**
- **Why it matters**
- **Recommended fix**

Only report actionable findings. Separate confirmed issues from optional improvements.

## Final Report

Return:

- **Critical**
- **High**
- **Medium**
- **Low**
- **Positive Findings**
- **Overall Assessment**
- **Recommended Priority**
