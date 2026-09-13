# Implementation Order & Progress

This log records the ordered execution of SRS requirements (see `SRS.md`). Only entries with
**Status: Implemented** have merged code.

---

## Phase 3 - Approval & Security Controls

### 3.x Approval Workflow & Requests engine (SRS 0.8, 7.36, 7.37; V1.1 #40)

**Status:** Implemented

**Scope of this slice**

- Approval *workflow/rule/step* configurability and an *approval request* runtime, wired to
  journal entries, replacing raw per-entry single-approver flow only when a matching workflow
  exists. When no workflow matches, legacy single-approver behavior is preserved.
- Every approval action is immutable and references the request step (SRS 0.8: "Every approval
  action must reference the request and request step and must be immutable after creation").
- Maker-checker: the requester can never be an approver of the same request step.
- Parallel steps enforce distinct-approver minimums; sequential steps enforce prior-step
  approvals (ladder); no parallel-with-sequential laddering yet (V1.1 refinement).
- `creator != final poster` hardening of `PostJournalEntry` is NOT part of this slice (existing
  Reports/Dashboard tests post as submitter); captured as the next eligible item.

**Evidence / tests**

- `tests/Feature/ApprovalWorkflowTest.php` - 19 tests (workflow resolution by amount tiers and
  account scoping, request creation, legacy fallback, maker-checker, sequential and parallel
  step execution, duplicate-approver rejection, reject-with/without reason, permission denials,
  cross-org denial, withdrawal, cancel, action immutability, audit lifecycle, HTTP flow + 403).
- Full suite: **91 tests passing** (72 pre-existing + 19 new). Cover: appended.
- Pint clean.

**Remaining Phase 3 items**

- `PostJournalEntry` hardening: require final poster to differ from entry creator.
- Audit-log tamper evidence: add verification/expected-chain tooling on top of the existing
  hash chain writer (`AuditLogService`).
- MFA / re-auth hook verification: confirm Fortify 2FA + passkeys cover the sensitive routes
  called out in SRS 7.36.