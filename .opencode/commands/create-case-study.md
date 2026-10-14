---
name: create-case-study
description: Create an Evidence-Based Project Case Study
---

**Topic/Project:** `$ARGUMENTS`

Analyze the existing project and create a professional Markdown case study that demonstrates the problem solved, engineering decisions, implementation quality, and verified outcomes.

## Workflow

1. Load and read only skills under `.agents/skills/` that are necessary to perform this before drafting. Locate it in the workspace or provided files; if unavailable, ask for it rather than inventing its contents.
2. Inspect relevant project documentation, source code, architecture, database schema, API definitions, tests, and available Git history or screenshots.
3. Identify the actual features implemented, major technical challenges, important design decisions, and available evidence of correctness or impact.
4. Explain why significant decisions were made. Where evidence permits, compare alternatives and discuss trade-offs.
5. Use tests, benchmarks, deployment evidence, or other reliable sources to support claims. Do not imply that unverified functionality, testing, deployment, or business impact has been achieved.
6. Create the case study following the checklist, adapting its sections to the project.

## Required Content

- Project overview, my role/contribution when known, timeline, and tech stack
- Problem and target users
- Goals and implemented requirements
- Solution overview and key features
- Architecture, data model, and important technical decisions where relevant
- Implementation challenges and how they were addressed
- Testing, validation, security, accessibility, or performance evidence where applicable
- Results and impact supported by evidence
- Lessons learned, technical limitations, and prioritized future improvements

Include useful screenshots, architecture diagrams, code examples, or links to relevant files when available. Explain their relevance instead of adding them as decoration.

## Output Requirements

- Save as `docs/case-studies/<project-slug>.md`, unless another output path is provided.
- Write for recruiters and technical reviewers: keep the main narrative scannable and include deeper technical details where they provide value.
- Separate verified facts from assumptions, estimates, and recommendations.
- Never fabricate metrics, user feedback, business outcomes, or personal contributions.
- Keep the case study specific to the inspected project; do not produce a generic project description.
- Do not modify application code or unrelated project files.

Finally, audit the document against `.agents/skills/case-study-checklist.md`. Report any checklist gaps, missing evidence, and claims that require confirmation.
