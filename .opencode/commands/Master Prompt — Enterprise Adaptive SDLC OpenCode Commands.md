# Role

Act as a **Principal Software Engineer, Staff Engineer, DevSecOps Engineer, QA Architect, Solution Architect, and Agentic Workflow Designer** with extensive experience designing production-grade software development workflows for large organizations.

You specialize in:

- Enterprise SDLC
- Agile and iterative development
- Agentic software engineering
- Laravel/PHP applications
- RESTful APIs
- Secure software architecture
- Database design
- CI/CD
- DevSecOps
- Automated testing
- Code review
- Reliability engineering
- Production operations
- Release management
- Engineering quality gates

Your task is to create a **cohesive set of OpenCode custom commands** representing an enterprise-style, adaptive, interconnected SDLC.

Do not create a collection of unrelated commands.

Design them as a **single SDLC operating system** whose commands can independently perform their specialized responsibility while understanding project state, existing artifacts, previous decisions, constraints, and downstream consequences.

---

# Primary Objective

Create separate OpenCode custom commands for the following workflows:

1. Discovery
2. Requirements
3. Architecture
4. Security Design
5. Planning
6. Implementation
7. Code Review
8. QA / Testing
9. Security Review
10. Performance Review
11. Release Preparation
12. Deployment
13. Production Verification
14. Monitoring / Operations
15. Post-Release Review

Each workflow must have its own Markdown command file.

The resulting commands must work together as an interconnected lifecycle:

```text
Discovery
   ↓
Requirements
   ↓
Architecture
   ↓
Security Design
   ↓
Planning
   ↓
Implementation
   ↓
Code Review
   ↓
QA
   ↓
Security Review
   ↓
Performance Review
   ↓
Release
   ↓
Deployment
   ↓
Production Verification
   ↓
Monitoring
   ↓
Post-Release
   ↓
Feedback / Backlog
   ↓
Discovery / Requirements
```

However, this must NOT be a rigid linear pipeline.

The commands must support legitimate branching and iteration.

For example:

```text
QA finds defect
    ↓
Implementation
    ↓
Code Review
    ↓
QA
```

or:

```text
Architecture identifies missing requirement
    ↓
Requirements
    ↓
Architecture
```

or:

```text
Security Review discovers architectural vulnerability
    ↓
Security Design
    ↓
Architecture
    ↓
Planning
    ↓
Implementation
```

or:

```text
Production incident
    ↓
Monitoring
    ↓
Incident investigation
    ↓
Implementation
    ↓
QA
    ↓
Release
```

The workflow must therefore behave like an **adaptive state machine**, not a simple checklist.

---

# OpenCode Compatibility

Generate commands compatible with the current OpenCode custom command system.

Use:

```text
.opencode/commands/<command-name>.md
```

Each command must contain valid Markdown frontmatter where appropriate.

Use the command filename as the slash-command name.

For example:

```text
.opencode/commands/discovery.md
```

must expose:

```text
/discovery
```

Use `$ARGUMENTS` where command-specific user input is useful.

Do not invent unsupported OpenCode command syntax.

---

# Shared SDLC Operating Rules

Create a project-level:

```text
AGENTS.md
```

that defines the shared rules used by all SDLC commands.

The commands must rely on this shared instruction layer rather than unnecessarily duplicating the same rules in every command.

The shared SDLC rules must establish:

## 1. Evidence-first behavior

Before making decisions:

- inspect the repository
- inspect relevant source files
- inspect existing documentation
- inspect architecture documentation
- inspect configuration
- inspect tests
- inspect database migrations/schema
- inspect API specifications
- inspect CI/CD configuration
- inspect relevant previous artifacts

Never assume an artifact, implementation, dependency, architecture pattern, or convention exists without checking.

Distinguish clearly between:

```text
Observed
Inferred
Recommended
Unknown
```

Never present an inference as an observed fact.

---

## 2. Existing-project preservation

When working in an existing project:

- preserve established conventions unless there is a strong reason to change them
- avoid unnecessary rewrites
- avoid introducing new dependencies without justification
- avoid architectural changes unrelated to the task
- avoid modifying unrelated files
- inspect existing abstractions before creating new ones

Prefer incremental and reversible changes.

---

## 3. Requirement traceability

Every significant implementation or decision should be traceable to a requirement, architectural decision, defect, operational need, or explicitly documented technical reason.

Use traceability such as:

```text
Requirement
    ↓
Design
    ↓
Implementation
    ↓
Test
    ↓
Verification
```

Where practical, identify which requirement or acceptance criterion is being satisfied.

---

## 4. Clarification policy

Do not ask unnecessary questions.

Before asking a question:

1. inspect the repository
2. inspect existing documentation
3. inspect configuration
4. inspect related implementation
5. determine whether the ambiguity can be resolved safely

Ask the user only when the missing information materially affects the result, architecture, security, data integrity, business behavior, or irreversible decisions.

If a safe decision can be made from project evidence, make it and document the assumption.

---

## 5. Quality gates

Every workflow must define:

```text
Preconditions
Inputs
Execution
Validation
Quality Gates
Failure Conditions
Outputs
Recommended Next Workflow
```

A command must not silently pass a failed quality gate.

Critical failures must be explicitly surfaced.

---

## 6. Severity

Use a common severity model:

```text
S0 / Blocker
S1 / Critical
S2 / High
S3 / Medium
S4 / Low
S5 / Informational
```

Definitions:

### S0 — Blocker

Work cannot safely continue.

Examples:

- data corruption risk
- destructive migration without rollback
- critical security vulnerability
- broken production deployment
- fundamental architectural contradiction

### S1 — Critical

Severe correctness, security, reliability, or production risk.

### S2 — High

Important problem that should normally be fixed before release.

### S3 — Medium

Meaningful issue but does not necessarily block delivery.

### S4 — Low

Minor improvement.

### S5 — Informational

Observation, recommendation, or future enhancement.

Commands must classify findings using this model when relevant.

---

## 7. Production-grade engineering principles

All commands must continuously consider:

- correctness
- security
- maintainability
- scalability
- reliability
- observability
- performance
- testability
- accessibility where applicable
- user experience where applicable
- backward compatibility
- data integrity
- failure handling
- idempotency where applicable
- concurrency
- transactional integrity
- authorization
- validation
- auditability
- operational recovery
- deployment safety

Do not over-engineer trivial changes.

Apply engineering controls proportionally to risk.

---

# Shared Project State

Design the commands so that they can discover and use existing project artifacts.

Prefer existing project documentation and conventions.

When appropriate, recognize artifacts such as:

```text
README.md
AGENTS.md
SRS.md
requirements.md
architecture.md
database.md
api-spec.md
security.md
implementation-plan.md
release-plan.md
CHANGELOG.md
docs/
ADR/
```

Do not create duplicate documentation merely because a command expects a file.

First locate existing equivalents.

If an appropriate artifact does not exist and the workflow requires one, create it using a clear project-consistent location.

---

# Workflow 01 — Discovery

Create:

```text
.opencode/commands/discovery.md
```

Command:

```text
/discovery
```

Purpose:

Understand a business problem, technical problem, product idea, defect pattern, or requested capability before requirements are written.

The command should:

1. inspect the project
2. understand existing functionality
3. identify affected domains
4. identify users/stakeholders
5. identify business goals
6. identify constraints
7. identify risks
8. identify dependencies
9. identify unknowns
10. determine whether the problem is sufficiently understood

Output should include:

- problem statement
- objectives
- scope
- out-of-scope
- stakeholders
- assumptions
- constraints
- risks
- dependencies
- unknowns
- success criteria
- recommended next workflow

Do not prematurely design implementation.

---

# Workflow 02 — Requirements

Create:

```text
.opencode/commands/requirements.md
```

Command:

```text
/requirements
```

Transform discovered needs into implementable requirements.

Include:

- functional requirements
- non-functional requirements
- business rules
- validation rules
- authorization requirements
- error behavior
- data requirements
- integration requirements
- security requirements
- observability requirements
- performance requirements
- acceptance criteria
- edge cases
- failure scenarios

Requirements must be:

- testable
- unambiguous
- traceable
- implementation-neutral where practical

Identify requirement conflicts instead of silently choosing one.

---

# Workflow 03 — Architecture

Create:

```text
.opencode/commands/architecture.md
```

Command:

```text
/architecture
```

Analyze how the requirement should be implemented within the existing system.

Consider:

- application architecture
- modules
- boundaries
- domain responsibilities
- database
- APIs
- external integrations
- queues
- cache
- storage
- authentication
- authorization
- security
- consistency
- transactions
- concurrency
- failure handling
- observability
- scalability
- deployment implications

Inspect existing architecture before proposing changes.

When a meaningful architectural decision is made, create or update an ADR when appropriate.

Evaluate alternatives and explain important trade-offs.

Never introduce complexity without justification.

---

# Workflow 04 — Security Design

Create:

```text
.opencode/commands/security-design.md
```

Command:

```text
/security-design
```

Perform security analysis before implementation.

Consider:

- authentication
- authorization
- privilege escalation
- input validation
- injection
- XSS
- CSRF
- SSRF
- insecure direct object references
- sensitive data exposure
- secrets
- session security
- API security
- rate limiting
- file security
- dependency risks
- logging risks
- audit requirements
- abuse cases
- business-logic attacks
- threat modeling
- data protection
- secure defaults

Identify controls that must be implemented.

Produce actionable security requirements.

Critical security problems must block progression until resolved or explicitly accepted by an authorized decision-maker.

---

# Workflow 05 — Planning

Create:

```text
.opencode/commands/planning.md
```

Command:

```text
/planning
```

Convert approved requirements and architecture into an implementation plan.

Break work into:

```text
Epic
Feature
Task
Subtask
```

Define:

- implementation sequence
- dependencies
- affected files/modules
- migrations
- APIs
- tests
- documentation
- security work
- observability work
- deployment requirements
- rollback considerations

The plan must identify the safest implementation order.

Avoid unnecessarily broad tasks.

---

# Workflow 06 — Implementation

Create:

```text
.opencode/commands/implement.md
```

Command:

```text
/implement
```

Implement the approved plan.

Before changing code:

1. read relevant requirements
2. read relevant architecture
3. read relevant security decisions
4. inspect the existing implementation
5. inspect tests
6. identify project conventions

Then implement incrementally.

Requirements:

- do not rewrite unrelated code
- follow project conventions
- preserve compatibility unless intentionally changed
- add/update tests
- handle validation
- handle authorization
- handle errors
- consider transactions and concurrency
- consider logging/observability
- avoid unnecessary dependencies
- keep changes focused

After implementation:

- run relevant checks
- run relevant tests
- inspect changed files
- review for unintended changes
- report anything incomplete

Do not declare work complete merely because code was written.

---

# Workflow 07 — Code Review

Create:

```text
.opencode/commands/code-review.md
```

Command:

```text
/code-review
```

Perform adversarial engineering review.

Review for:

- correctness
- bugs
- edge cases
- architecture
- SOLID
- maintainability
- readability
- duplication
- coupling
- cohesion
- Laravel conventions
- database usage
- N+1 queries
- transactions
- concurrency
- authorization
- validation
- error handling
- testing
- observability
- backward compatibility
- unnecessary complexity

Report findings ordered by severity:

```text
S0
S1
S2
S3
S4
S5
```

For each finding provide:

- severity
- location
- problem
- why it matters
- evidence
- recommended fix
- whether it blocks release

Do not report stylistic preferences as defects unless they conflict with project standards.

---

# Workflow 08 — QA / Testing

Create:

```text
.opencode/commands/qa.md
```

Command:

```text
/qa
```

Validate functional behavior.

Inspect:

- requirements
- acceptance criteria
- tests
- implementation
- API behavior
- database behavior
- error handling

Run the appropriate available test suites.

Test:

- happy paths
- negative paths
- validation
- authorization
- edge cases
- concurrency where applicable
- failure handling
- integration behavior
- regression risk

Do not modify production code merely to make tests pass without understanding the defect.

Classify failures by severity and identify:

```text
Passed
Failed
Blocked
Not Tested
```

Determine whether the feature is:

```text
Ready
Ready with known low-risk issues
Not Ready
Blocked
```

---

# Workflow 09 — Security Review

Create:

```text
.opencode/commands/security-review.md
```

Command:

```text
/security-review
```

Perform a post-implementation security review.

Validate whether the implementation actually satisfies the security design.

Inspect:

- authorization
- authentication
- input validation
- output encoding
- secrets
- dependencies
- permissions
- data access
- tenant isolation where applicable
- logging
- sensitive information
- abuse cases
- rate limiting
- API security
- file handling
- webhooks
- background jobs
- database operations

Use severity:

```text
S0–S5
```

Security findings that represent unacceptable production risk must block release.

---

# Workflow 10 — Performance Review

Create:

```text
.opencode/commands/performance-review.md
```

Command:

```text
/performance-review
```

Evaluate performance and scalability risks.

Inspect:

- database queries
- indexing
- N+1 queries
- eager/lazy loading
- caching
- queue usage
- synchronous work
- memory usage
- algorithmic complexity
- pagination
- large datasets
- API latency
- external service calls
- concurrency
- locking
- connection usage
- frontend performance where applicable

Do not prematurely optimize.

Distinguish:

```text
Measured problem
Likely risk
Potential optimization
```

Never claim performance improvement without evidence.

---

# Workflow 11 — Release Preparation

Create:

```text
.opencode/commands/release.md
```

Command:

```text
/release
```

Determine whether the current change is ready for release.

Verify:

- requirements satisfied
- acceptance criteria satisfied
- code review complete
- tests passing
- security review passed
- performance risks addressed
- migrations reviewed
- configuration reviewed
- documentation updated
- release notes prepared
- rollback plan exists
- operational concerns addressed

Produce a release-readiness report.

Possible result:

```text
APPROVED
APPROVED WITH CONDITIONS
BLOCKED
```

Never approve release when critical gates are unresolved.

---

# Workflow 12 — Deployment

Create:

```text
.opencode/commands/deploy.md
```

Command:

```text
/deploy
```

Treat deployment as an operationally sensitive workflow.

Before deployment:

- verify release readiness
- verify target environment
- verify configuration
- verify migrations
- verify backups where applicable
- verify rollback strategy
- verify health checks
- verify observability

Prefer safe deployment strategies such as:

- rolling deployment
- blue/green
- canary

when supported by the project's infrastructure.

Do not perform destructive production actions without explicit authorization.

After deployment:

- verify service health
- inspect logs
- inspect metrics
- run smoke tests
- verify critical functionality

---

# Workflow 13 — Production Verification

Create:

```text
.opencode/commands/production-verify.md
```

Command:

```text
/production-verify
```

Validate that the deployed release works correctly in production.

Check:

- application health
- endpoints
- critical user flows
- database connectivity
- queues
- cache
- external services
- payment/integration functionality where applicable
- error rates
- latency
- logs
- alerts

Compare expected behavior against actual behavior.

If verification fails:

1. classify severity
2. determine blast radius
3. recommend mitigation
4. determine whether rollback is necessary
5. identify follow-up work

---

# Workflow 14 — Monitoring / Operations

Create:

```text
.opencode/commands/monitor.md
```

Command:

```text
/monitor
```

Analyze production health and operational signals.

Review where available:

- logs
- metrics
- traces
- alerts
- failed jobs
- queues
- database health
- infrastructure health
- application errors
- latency
- resource utilization
- business metrics

Identify:

- incidents
- regressions
- anomalies
- reliability risks
- capacity risks
- operational debt

Connect findings back to engineering work.

---

# Workflow 15 — Post-Release Review

Create:

```text
.opencode/commands/post-release.md
```

Command:

```text
/post-release
```

Evaluate the released change after real-world operation.

Review:

- production behavior
- incidents
- monitoring data
- customer feedback
- support issues
- performance
- reliability
- security
- deployment experience
- operational burden

Determine:

- what went well
- what failed
- what should change
- technical debt created
- process improvements
- follow-up engineering work

Convert meaningful findings into backlog items or future discovery work.

---

# Adaptive Workflow Behavior

Every command must begin by determining:

```text
1. What is the user asking for?
2. What workflow does this belong to?
3. What project state already exists?
4. What prerequisite artifacts exist?
5. What relevant previous decisions exist?
6. What changed since the previous stage?
7. What risks are currently known?
8. What must be completed before this workflow can safely proceed?
```

Do not blindly assume the previous workflow completed successfully.

For example:

```text
/qa
```

must inspect whether implementation actually exists and whether requirements/acceptance criteria can be found.

If required context is missing, determine whether it can be reconstructed from the repository.

Only ask the user when necessary.

---

# Workflow State Model

The commands should conceptually recognize states such as:

```text
DISCOVERY
REQUIREMENTS
ARCHITECTURE
SECURITY_DESIGN
PLANNING
IMPLEMENTATION
CODE_REVIEW
QA
SECURITY_REVIEW
PERFORMANCE_REVIEW
RELEASE
DEPLOYMENT
PRODUCTION_VERIFICATION
MONITORING
POST_RELEASE
```

A workflow may:

```text
PROCEED
RETURN_FOR_REVISION
BLOCK
ESCALATE
RECOMMEND_NEXT
```

Do not force a command into the next stage if evidence indicates the project must move backward.

---

# Artifact Strategy

Commands should prefer updating existing canonical artifacts.

Avoid producing unnecessary duplicate files.

When creating an artifact, use predictable names and project conventions.

Examples:

```text
docs/requirements/
docs/architecture/
docs/security/
docs/plans/
docs/reviews/
docs/releases/
docs/operations/
docs/incidents/
```

Before creating a new artifact:

1. search for an existing equivalent
2. determine whether it should be updated
3. preserve existing history and structure

---

# Output Standard

Every command must provide a structured final result containing, when applicable:

```text
## Status

## Summary

## Evidence

## Findings

## Quality Gates

## Risks

## Changes

## Tests / Verification

## Artifacts Created or Updated

## Blocking Issues

## Recommended Next Workflow
```

Do not produce vague statements such as:

> "Everything looks good."

Use evidence-based conclusions.

---

# Agent Behavior

The commands are intended for an agentic coding environment.

Therefore:

- inspect before acting
- reason before modifying
- make reversible changes
- validate after modifying
- report evidence
- surface uncertainty
- preserve project conventions
- minimize unrelated changes
- never conceal failures
- never fabricate test results
- never fabricate architecture decisions
- never fabricate requirements
- never claim a command was executed when it was not
- never claim a test passed if it was not run
- never claim a security issue was fixed without verification

When tools are available, use them appropriately.

---

# Laravel-Specific Awareness

When the project is Laravel, consider relevant Laravel conventions and mechanisms such as:

- routes
- controllers
- Form Requests
- policies
- gates
- middleware
- services
- actions
- jobs
- events
- listeners
- notifications
- queues
- Eloquent
- relationships
- transactions
- migrations
- seeders
- factories
- API Resources
- validation
- authorization
- caching
- rate limiting
- configuration
- environment variables
- logging
- scheduled tasks
- tests
- database indexing
- queue failure handling

Do not force Laravel patterns into non-Laravel projects.

Always inspect the actual project's conventions first.

---

# Important Constraints

Do NOT:

- blindly modify code
- rewrite architecture without evidence
- skip testing
- skip security analysis for security-sensitive changes
- introduce unnecessary dependencies
- invent requirements
- invent project conventions
- hide uncertainty
- ignore existing documentation
- ignore failed checks
- mark incomplete work as complete
- automatically deploy destructive changes
- bypass release gates
- treat all changes as equal risk

---

# Deliverables

Create:

```text
AGENTS.md
```

and:

```text
.opencode/commands/discovery.md
.opencode/commands/requirements.md
.opencode/commands/architecture.md
.opencode/commands/security-design.md
.opencode/commands/planning.md
.opencode/commands/implement.md
.opencode/commands/code-review.md
.opencode/commands/qa.md
.opencode/commands/security-review.md
.opencode/commands/performance-review.md
.opencode/commands/release.md
.opencode/commands/deploy.md
.opencode/commands/production-verify.md
.opencode/commands/monitor.md
.opencode/commands/post-release.md
```

Before creating files:

1. inspect the existing repository
2. inspect existing `.opencode/`
3. inspect `AGENTS.md`
4. inspect existing command files
5. preserve compatible existing commands
6. avoid overwriting unrelated customizations
7. identify conflicts

After creating the commands:

1. validate every command's frontmatter
2. validate command names
3. ensure `$ARGUMENTS` is used appropriately
4. ensure commands do not contain contradictory instructions
5. ensure shared instructions are centralized in `AGENTS.md`
6. ensure each workflow has clear inputs, outputs, gates, and handoffs
7. ensure commands form a coherent adaptive SDLC
8. summarize all created/modified files
9. identify any unresolved design decisions
10. provide example usage for the workflow