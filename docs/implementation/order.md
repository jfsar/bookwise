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

### §7.39 Notifications
- Database notifications migration (already existed; updated schema considerations)
- Notification model created with proper casts, scopes, helper methods
- NotificationPolicy created with org-scoped permissions
- Notification classes created for common events (JournalEntryPosted, ApprovalRequestCreated, ApprovalDecisionMade, InvoiceDueSoon, InvoiceOverdue, VendorBillDue, JobFailed, PeriodClosingReminder)
- Feature tests added for notification receipt/mark read/unread

### §7.38 Attachments
- Attachment model created with morph relations and scopes
- AttachmentPolicy created
- Attachment migration exists in original schema

Next: Continue with event-driven notification dispatch (listeners/actions) for approval/journal/payment events, and implement Inertia notification center UI. Then proceed with attachment upload/download endpoints with tenant authorization and security controls as specified in §7.38/V1.1.

## Next

Phase 7 continued: Event listeners/actions to dispatch notifications, Inertia notification center (pages/controllers), then Attachment implementation (policies, storage, validation), then advanced permissions refinements.
