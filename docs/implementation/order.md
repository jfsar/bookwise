# Implementation Order & Progress

This log tracks SRS requirement execution (see `SRS.md`). Only entries with **Status: Implemented** have merged code.

## Phase Status

| Phase                                            | Status      |
| ------------------------------------------------ | ----------- |
| 0 — Foundation & Schema Hardening                | complete    |
| 1 — MVP Accounting Core                          | complete    |
| 2 — Ledger & Core Reporting                      | complete    |
| 3 — Approval & Security Controls                 | complete    |
| 4 — Accounts Receivable                          | complete    |
| 5 — Accounts Payable                             | complete    |
| 6 — Banking                                      | complete    |
| 7 — Enterprise & Operational Features            | in_progress |
| 8 — Reporting Optimization & Derived Performance | not_started |
| 9 — Reliability & Production Hardening           | not_started |

## Phase 7 — Enterprise & Operational Features (§7.39 Notifications, §7.38 Attachments) — Status: in_progress

### §7.39 Notifications — Status: implemented
- `OrganizationNotification` contract + `organization-database` channel persist `notifications.organization_id` for every tenant notification; `User::notifications()` hydrates `App\Models\Notification`.
- Approval dispatch wired through `ApprovalWorkflowService` (after-commit) to `ApprovalRequestCreated` / `ApprovalDecisionMade`; `JournalPosted` notifies the entry creator.
- Notification center: `NotificationController` (index, mark read/unread, mark all read), org-scoped `NotificationPolicy`, routes, `unreadNotificationsCount` shared prop, Inertia `Notifications/Index.jsx` page and header bell badge.
- Scheduled reminders: `SendInvoiceDueReminders`, `SendVendorBillDueReminders`, `SendPeriodClosingReminders` + commands/schedules, idempotent per recipient/subject/run day via `SkipsDuplicateReminders`.
- Failed background jobs: `OrganizationScopedJob` contract, `ExportReportJob` attribution, `NotifyOrganizationAdminsOfFailedJob` notifies org admins with `manage-settings` and reports unattributable failures.
- Tests: `NotificationTest`, `NotificationCenterTest`, `NotificationReminderTest`, `ApprovalNotificationTest`, `FailedJobNotificationTest`.

### §7.38 Attachments
- Attachment model created with morph relations and scopes
- AttachmentPolicy created
- Attachment migration exists in original schema

## Next

Phase 7 continued: attachment upload/download/delete endpoints with tenant authorization and the §0.27 file security controls (private storage, MIME/size validation, malware scan, signed download URLs), then advanced permissions refinements.
