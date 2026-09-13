---
description: Read and plan one specific Section 7 SRS requirement before implementation
agent: build
---

Create a detailed implementation plan for the Section 7 requirement `$ARGUMENTS` in `SRS.md`.

Do not implement code yet.

## Required workflow

1. Resolve the exact requirement from `SRS.md`.
2. Read the complete local context of that requirement, including its parent Section 7 subsection and adjacent requirements that materially affect it.
3. Read relevant Version 1.1 architecture corrections, invariants, database schema, business rules, NFRs, testing requirements, acceptance criteria, and implementation-order guidance.
4. Inspect the existing repository and identify current models, migrations, actions/services, policies, Livewire/Blade, routes, jobs/events, and tests related to the requirement.
5. Determine the smallest production-quality change set that satisfies the SRS without inventing additional scope.

## Produce the plan

Create/update:

`docs/implementation/section-7/$ARGUMENTS.md`

Use these headings:

# Requirement `$ARGUMENTS`
## 1. SRS interpretation
## 2. Supporting requirements
## 3. Current implementation state
## 4. Goals
## 5. Non-goals
## 6. Proposed design
## 7. Database changes
## 8. Application/domain changes
## 9. UI changes
## 10. Authorization and security
## 11. Tests
## 12. Verification commands
## 13. Acceptance checklist
## 14. Risks and ambiguities

For every goal, state how it will be verified.

Do not invent behavior that is not supported by the SRS or repository conventions. Record unresolved ambiguity explicitly.
