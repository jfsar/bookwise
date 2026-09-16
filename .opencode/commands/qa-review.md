---
description: Perform an evidence-based agentic QA and code review focused on correctness, security, scalability, architecture, and UX
agent: plan
---

Review the code described by $ARGUMENTS.

$ARGUMENTS may contain files, directories, classes, functions, React components, pages, APIs, routes, features, or specific review concerns.

The review is read-only.

- Do not modify project files.
- Do not implement fixes.
- Provide evidence-based findings and actionable recommendations only.

## Before Reviewing

### Load Project Skills

- Inspect the available project skills under `.agents/skills/`.
- Identify every skill relevant to the code being reviewed.
- Load and follow each relevant `SKILL.md`.
- Treat loaded skills as project-specific review criteria.
- Do not load unrelated skills.

### Establish Review Scope

- Determine the requested review scope and concerns.
- Inspect the target code and surrounding implementation.
- Inspect related code, usages, dependencies, configuration, tests, and integration points when necessary.
- Do not assume behavior that cannot be supported by repository evidence.

## Review Workflow

### 1. Understand the Implementation

- Identify the purpose and expected behavior of the reviewed code.
- Trace relevant execution and data flows.
- Identify dependencies, interfaces, contracts, and integration points.

### 2. Inspect & Validate

- Search related usages and similar implementations where necessary.
- Inspect existing tests and available test coverage.
- Inspect applicable project conventions, architecture, configuration, and documentation.
- Inspect available test, lint, type-check, static-analysis, and build commands.
- Run relevant checks when practical.
- Report exactly which checks were executed and their results.
- Do not claim a check passed if it was not executed.

### 3. Review Areas

Evaluate the relevant implementation for:

- **Correctness & QA** — bugs, edge cases, error handling, regressions, state consistency, test coverage
- **Project Conventions** — Laravel, Inertia, React, naming, structure, patterns, consistency
- **Architecture & SOLID** — separation of concerns, coupling, cohesion, duplication, abstraction quality, maintainability
- **Scalability & Performance** — database queries, N+1 issues, indexing, caching, rendering, network requests, queues, resource usage
- **Security** — authentication, authorization, validation, injection, XSS, CSRF, mass assignment, data exposure, secrets, rate limiting
- **UI/UX** — usability, consistency, responsive behavior, loading/error/empty states, accessibility, keyboard navigation, feedback
- **Reliability & Observability** — failure handling, logging, idempotency, monitoring, recoverability, resilience
- **Testing** — unit, feature/integration, frontend, edge-case, and regression coverage

Only evaluate areas relevant to the reviewed implementation.

### 4. Evidence & Findings

- Report only actionable findings supported by sufficient evidence.
- Do not report purely theoretical or speculative issues as confirmed findings.
- Clearly distinguish confirmed issues from optional improvements.
- For each finding, identify the affected code and supporting evidence.
- Assign severity based on demonstrated impact and evidence.

## Severity

Classify every confirmed finding as:

**Critical** — security vulnerability, data loss/corruption, severe production failure, or system-breaking issue.

**High** — significant correctness, security, performance, scalability, or maintainability problem likely to cause real impact.

**Medium** — meaningful defect, architectural weakness, UX/accessibility problem, or missing test that should be addressed.

**Low** — minor convention, readability, consistency, or non-critical improvement.

Order confirmed findings from highest to lowest severity.

For each finding include:

- **Severity**
- **Location**
- **Evidence**
- **Issue**
- **Why it matters**
- **Recommended fix**

## Optional Improvements

Separate non-defect suggestions from confirmed findings.

Use this section for improvements that would increase maintainability, developer experience, usability, performance, or consistency but are not demonstrated defects.

## Final Report

Return:

### Critical

Confirmed critical findings, or `None`.

### High

Confirmed high findings, or `None`.

### Medium

Confirmed medium findings, or `None`.

### Low

Confirmed low findings, or `None`.

### Optional Improvements

Non-defect improvements, or `None`.

### Positive Findings

Document strengths, good patterns, effective safeguards, useful tests, or well-implemented design decisions.

### Overall Assessment

Summarize the verified state of the reviewed implementation, including major strengths, confirmed risks, and significant gaps.

Do not assign a numerical, letter, or overall quality score.

### Recommended Priority

Provide a practical remediation order for the confirmed findings based on severity, impact, dependencies, and implementation considerations.

Do not rank the implementation or provide an overall quality ranking.
