# BookWise Accounting System

> A production-style, modular Laravel accounting application built around a reliable double-entry general ledger.

**SRS:** v1.1 — Architecture & Database Corrections  
**Application type:** Server-rendered Laravel modular monolith  
**Frontend:** Blade + Livewire + Tailwind CSS  
**Backend:** Laravel / PHP  
**Database:** PostgreSQL  
**Cache & queues:** Redis  
**API:** Not required for Version 1  
**Status:** Development specification

---

## What is this project?

This project is a full accounting application designed to demonstrate how a serious financial system can be designed and implemented using Laravel.

The system is centered on one fundamental accounting rule:

```text
Every posted accounting transaction must have:

Total Debits = Total Credits
```

Instead of treating invoices, payments, banking, and reports as independent sources of financial truth, the application uses the **general ledger as the financial source of truth**. Business modules create accounting transactions, which ultimately become journal entries and posted ledger lines.

```text
Business Transaction
        ↓
   Domain Operation
        ↓
   Accounting Engine
        ↓
    Journal Entry
        ↓
  Immutable Ledger
        ↓
 ┌──────┼──────────┐
 ↓      ↓          ↓
Trial  Income   Balance
Balance Statement Sheet
```

This makes the project useful both as an accounting application and as a demonstration of professional backend engineering.

---

## Why build it?

The project is intentionally designed to demonstrate much more than CRUD.

It showcases:

- Double-entry accounting and ledger design
- Financial data integrity and immutability
- Multi-tenant data isolation
- Transactional database operations
- Concurrency control
- Maker-checker / segregation-of-duties controls
- Approval workflows
- Accounts receivable and accounts payable
- Bank reconciliation
- Multi-currency transactions
- Audit trails
- Database constraints and composite foreign keys
- Reporting and drill-down workflows
- Laravel Actions and domain services
- Queues, events, scheduled jobs, and notifications
- Automated accounting and authorization tests
- Production-oriented security, observability, backup, and recovery design

The goal is to show how an application can protect financial correctness at the **domain, application, and database levels**.

---

## Core accounting philosophy

### The ledger is the source of truth

Financial reports must be derived from posted journal lines rather than maintaining competing balances in multiple modules.

```text
AR ───────┐
AP ───────┤
Bank ─────┤
Manual ───┼──→ Accounting Engine ──→ Journal ──→ Ledger
Taxes ────┤                                      ↓
Other ────┘                                Financial Reports
```

This prevents situations where accounts receivable, the general ledger, and dashboard balances disagree.

### Posted financial records are immutable

Once a journal is posted, it cannot simply be edited or deleted.

Corrections are represented by new accounting transactions:

```text
Original Journal
       ↓
    Reversal
       ↓
 Corrected Entry
```

The original transaction remains visible for auditability.

### Database integrity matters

Application validation is not considered sufficient for financial data. Critical relationships and invariants are reinforced with PostgreSQL foreign keys, unique constraints, check constraints, transaction boundaries, row locks, and carefully designed indexes.

---

# Main features

## General Ledger

The accounting core supports:

- Chart of accounts
- Parent/child account hierarchy
- Posting and non-posting accounts
- Fiscal years
- Accounting periods
- Journal types
- Manual journal entries
- Journal approval
- Journal posting
- Journal reversal
- Recurring journals
- General ledger
- Trial balance

## Financial statements

The system provides:

- Income Statement
- Balance Sheet
- Cash Flow Statement
- Trial Balance
- General Ledger
- Journal Report

Reports support filtering and drill-down from financial statements to accounts, journal entries, and journal lines.

## Accounts Receivable

- Customers
- Customer addresses
- Customer invoices
- Invoice lines
- Customer payments
- Payment allocations
- Customer credit notes
- AR aging
- Customer statements

Typical invoice accounting:

```text
Accounts Receivable   Debit
Revenue               Credit
Tax Payable           Credit
```

## Accounts Payable

- Vendors
- Vendor addresses
- Vendor bills
- Bill lines
- Vendor payments
- Payment allocations
- Vendor debit notes
- AP aging
- Vendor statements

Typical bill accounting:

```text
Expense / Asset       Debit
Accounts Payable      Credit
```

## Banking

- Bank accounts
- Bank transactions
- Statement imports
- Transaction matching
- Reconciliation sessions
- Reconciliation matches
- Reconciliation history

The reconciliation model supports matching multiple accounting lines to a bank transaction and vice versa rather than assuming a one-to-one relationship.

## Tax

The tax subsystem supports:

- Tax codes
- Tax rates
- Tax accounts
- Exemptions
- Effective dates
- Historical applied tax values

Tax configuration is jurisdiction-neutral so the accounting engine is not hardcoded to a single country's rules.

## Multi-currency

Version 1.1 supports a transaction currency together with the organization's functional/base currency.

Posted lines preserve the values used at posting time rather than recalculating historical transactions from future exchange rates.

```text
Transaction Currency
        ↓
Transaction Amount
        ↓
Exact Applied FX Rate
        ↓
Base Currency Amount
```

Advanced FX revaluation and consolidation remain future extensions.

## Approval workflows

Approval can be configured based on factors such as:

- Transaction type
- Amount thresholds
- Account
- Organization
- Required role or permission
- Approval sequence

The design supports **maker-checker controls** so the creator of a transaction is not automatically allowed to approve or finalize the same transaction where segregation of duties is required.

## Audit trail

Significant accounting and security actions are recorded in an append-only audit log, including:

- User
- Organization
- Action
- Entity
- Entity identifier
- Before/after values where appropriate
- IP address
- User agent
- Request identifier
- Timestamp

Audit history is treated as a protected financial control, not as ordinary application logs.

---

# Multi-tenancy

Each organization is an independent accounting entity.

Users may belong to one or more organizations, subject to membership and authorization rules.

Tenant isolation is enforced at multiple layers:

```text
Request
  ↓
Organization Context
  ↓
Policy / Authorization
  ↓
Domain Operation
  ↓
Organization-scoped Query
  ↓
Database Constraints / Foreign Keys
```

Where records reference other tenant-owned records, the database uses organization-scoped relationships so an Organization A record cannot accidentally reference Organization B data.

For example:

```text
Organization A
   ↓
Journal Entry A
   ↓
Journal Line A
   ↓
Account A
```

must never be able to reference:

```text
Account B
```

---

# Accounting transaction lifecycle

A typical journal follows this state machine:

```text
Draft
  ↓
Submitted
  ↓
Approved
  ↓
Posted
  ↓
Reversed (when correction is required)
```

Other terminal or controlled states include:

```text
Rejected
Cancelled
```

The domain layer owns state transitions. Controllers and Livewire components do not directly manipulate posted accounting records.

---

# Journal posting

Posting is one of the most protected operations in the system.

Conceptually:

```text
BEGIN TRANSACTION

Lock / validate posting context
Validate organization
Validate journal state
Validate accounting period
Validate accounts
Validate account status
Validate posting accounts
Validate approval requirements
Validate debit / credit lines
Calculate totals
Verify debits = credits
Persist immutable posting state
Write audit information
Create reliable post-commit events/jobs

COMMIT
```

If any required operation fails:

```text
ROLLBACK
```

No partial financial transaction may remain committed.

---

# Concurrency control

Financial systems must assume multiple users or workers can attempt the same operation at the same time.

The design explicitly handles cases such as:

- Two users posting the same journal
- Two users allocating the same payment
- Two users closing the same accounting period
- Concurrent approval actions
- Repeated scheduled jobs
- Retried bank imports

The implementation uses the appropriate combination of:

- Database transactions
- Row-level locking
- Unique constraints
- Atomic updates
- State-transition checks
- Idempotency keys
- Deterministic import identities

For example, payment allocation must lock the payment and affected invoice records, recalculate availability, and only then create the allocation.

---

# Fiscal years and closing

Fiscal years contain accounting periods and follow controlled lifecycle states.

```text
Open
 ↓
Closing
 ↓
Closed
 ↓
Archived
```

Closing a period prevents new normal postings for that period.

Year-end processing explicitly addresses:

- Closing revenue accounts
- Closing expense accounts
- Retained earnings
- Opening balances
- Carry-forward balances

The close operation is concurrency-safe so two workers cannot close the same period inconsistently.

---

# Data model overview

The database is relational and PostgreSQL-based.

Major areas include:

```text
Organizations
├── Users / Memberships
├── Roles / Permissions
├── Accounts
├── Fiscal Years
│   └── Accounting Periods
├── Journals
├── Journal Entries
│   └── Journal Entry Lines
├── Customers
│   └── Customer Invoices
│       └── Invoice Lines
├── Customer Payments
│   └── Payment Allocations
├── Customer Credit Notes
├── Vendors
│   └── Vendor Bills
│       └── Bill Lines
├── Vendor Payments
│   └── Payment Allocations
├── Vendor Debit Notes
├── Bank Accounts
│   └── Bank Transactions
│       └── Reconciliation Matches
├── Tax Codes / Rates
├── Approval Workflows / Requests / Actions
├── Audit Logs
├── Attachments
└── Outbox / Idempotency Records
```

### Monetary values

Money is stored using fixed-precision PostgreSQL `NUMERIC` values rather than floating-point types.

The base design uses precision appropriate for accounting amounts and preserves the exact exchange rate and applied base amount used when posting.

### Primary keys

Internal database relationships use efficient numeric identifiers such as `BIGINT`. Public-facing identifiers may use ULIDs where appropriate.

---

# Performance and scalability

Version 1.1 remains a **modular monolith** rather than prematurely introducing microservices.

The intended architecture is suitable for the project's target of:

- Thousands of journal entries
- Hundreds of thousands of journal lines
- Multiple organizations
- Multiple concurrent users

The application is designed so the ledger remains authoritative while reporting can later be optimized with derived read models or balance snapshots without creating a second accounting source of truth.

### Performance targets

Normal interactive requests target approximately:

```text
p50  < 200 ms
p95  < 500 ms
p99  < 1 second
```

depending on operation and environment.

Large reports and exports should run asynchronously when they exceed normal interactive workloads.

### Performance techniques

- Organization-aware composite indexes
- Composite indexes based on real query patterns
- PostgreSQL query planning and `EXPLAIN ANALYZE`
- Pagination for large lists
- Redis caching for suitable derived data
- Queued report generation
- Cached dashboard aggregates
- Partitioning strategy for very large audit/ledger datasets
- Avoiding N+1 ORM queries
- Bulk processing for imports and exports

The database should not be flooded with unnecessary indexes; indexes are selected according to real query patterns and measured query plans.

---

# Security model

Security is treated as a cross-cutting requirement.

The application uses:

- Secure password hashing
- Session protection
- CSRF protection
- Server-side validation
- Laravel authorization policies
- Route and Livewire authorization
- Domain-level authorization for sensitive operations
- Rate limiting
- Mass-assignment protection
- Secure file-upload handling
- Tenant isolation
- Append-only audit history
- Privileged-operation re-authentication where appropriate
- Stronger authentication controls for privileged users

### Sensitive banking data

Bank account numbers and related sensitive information must be protected appropriately. Only the minimum required data should be exposed in normal UI responses and logs.

### File security

Attachments use private storage and controlled download authorization. The system should validate file content, size, type, storage names, access permissions, and malware/security scanning requirements before accepting untrusted files.

---

# Application architecture

The project follows a modular monolith architecture.

```text
Browser
   ↓
Blade + Livewire
   ↓
Presentation / Application Layer
   ↓
Policies + Actions
   ↓
Domain Layer
   ├── Accounting
   ├── Receivables
   ├── Payables
   ├── Banking
   └── Reporting
   ↓
PostgreSQL

Redis
├── Cache
└── Queues

Object Storage
└── Attachments
```

Business logic should not live inside controllers or Livewire components.

A typical operation looks like:

```text
Livewire Component
        ↓
Action
        ↓
Domain Service
        ↓
Transaction
        ↓
Repository / Eloquent
        ↓
PostgreSQL
```

Example actions include:

```text
CreateJournalEntry
SubmitJournalEntry
ApproveJournalEntry
PostJournalEntry
ReverseJournalEntry
CreateInvoice
PostInvoice
ReceiveCustomerPayment
AllocateCustomerPayment
CreateVendorBill
PostVendorBill
PayVendor
ReconcileBankStatement
CloseAccountingPeriod
```

---

# Events, queues, and reliability

The application uses events and queues for work that should not block normal accounting requests, such as:

- Notifications
- Large financial reports
- Bank imports
- Recurring journal generation
- Invoice reminders
- Audit/archive maintenance

Financial posting itself remains transactionally synchronous where required for correctness.

For reliable post-commit processing, the architecture includes an **outbox strategy** so important events are not lost when application and database operations succeed or fail at different points.

Scheduled operations are idempotent to prevent duplicate financial transactions after retries.

---

# Reporting architecture

Required reports include:

- General Ledger
- Trial Balance
- Income Statement
- Balance Sheet
- Cash Flow Statement
- AR Aging
- AP Aging
- Customer Statement
- Vendor Statement
- Tax Report
- Journal Report
- Audit Report

Reports are based on posted accounting data only unless a report explicitly states otherwise.

Drill-down is supported conceptually as:

```text
Financial Statement
       ↓
Account
       ↓
Ledger Transactions
       ↓
Journal Entry
       ↓
Journal Lines
```

Large exports and expensive reporting workloads should use queued background processing rather than blocking interactive requests.

---

# Testing strategy

Accounting software needs tests for both normal behavior and impossible states.

### Unit tests

Examples:

- Debit/credit validation
- Journal balancing
- Account balance calculations
- Tax calculations
- Invoice totals
- Payment allocation
- AR/AP aging
- Currency calculations

### Feature tests

Examples:

```text
Create journal
Submit journal
Approve journal
Post journal
Reverse journal
Close period
Create invoice
Post invoice
Receive payment
Allocate payment
Create vendor bill
Pay vendor
Reconcile bank account
```

### Security/authorization tests

Examples:

```text
User from Organization A cannot access Organization B
Viewer cannot post journals
Accountant cannot close periods
Unauthorized user cannot approve transactions
```

### Concurrency tests

Tests should simulate races such as:

```text
Double posting
Double payment allocation
Duplicate period close
Duplicate recurring-journal execution
Duplicate bank import
```

### Accounting invariant tests

The strongest invariant is:

```text
For every posted journal:

SUM(debits) = SUM(credits)
```

And the balance sheet must satisfy:

```text
Assets = Liabilities + Equity
```

---

# Recommended implementation order

The project is intentionally designed for incremental implementation.

### Phase 1 — Accounting Core

```text
Organizations
Users / Memberships
Roles / Permissions
Currencies
Accounts
Fiscal Years
Accounting Periods
Journals
Journal Entries
Journal Entry Lines
```

### Phase 2 — Ledger & Reporting

```text
General Ledger
Trial Balance
Income Statement
Balance Sheet
Cash Flow
```

### Phase 3 — Accounts Receivable

```text
Customers
Invoices
Payments
Payment Allocations
Credit Notes
AR Aging
```

### Phase 4 — Accounts Payable

```text
Vendors
Bills
Payments
Payment Allocations
Debit Notes
AP Aging
```

### Phase 5 — Banking

```text
Bank Accounts
Bank Transactions
Statement Imports
Reconciliation
```

### Phase 6 — Enterprise Controls

```text
Approval Workflows
Maker-Checker Controls
Audit Logs
Recurring Journals
Notifications
Attachments
Outbox
Idempotency
```

---

# Future extensions

The core is designed to allow later additions without compromising the accounting engine.

Potential future modules include:

- Fixed asset accounting
- Inventory accounting
- Budgeting
- Purchase orders
- Sales orders
- Payroll
- Cost centers
- Departments
- Projects
- Multi-branch accounting
- Advanced foreign-exchange revaluation
- Consolidated financial statements
- Recurring billing
- Payment gateway integration
- Bank feeds
- Automated tax filing
- Document OCR
- Financial forecasting

These are intentionally kept outside the initial core so the accounting engine remains understandable and strongly consistent.

---

# What makes this project different from a CRUD application?

The project is not primarily about creating tables and forms.

The difficult parts are the accounting invariants and operational guarantees:

```text
Can a journal become unbalanced?
        ↓
       NO

Can a posted journal be silently edited?
        ↓
       NO

Can Organization A reference Organization B's account?
        ↓
       NO

Can two concurrent requests over-allocate one payment?
        ↓
       NO

Can a retry create the same accounting transaction twice?
        ↓
       NO

Can a closed period receive normal postings?
        ↓
       NO

Can an auditor trace who did what?
        ↓
       YES
```

That is the central engineering challenge of the project.

---

# Documentation

The authoritative requirements are defined in:

**`Double-Entry Accounting System — SRS v1.1 Architecture & Database Corrections.md`**

The v1.1 specification supersedes conflicting v1.0 behavior and contains the detailed:

- Functional requirements
- Business rules
- Database schema
- Database constraints
- Tenant-safe relationships
- Index strategy
- Concurrency contracts
- Approval model
- Accounting invariants
- Security requirements
- Reporting requirements
- Backup/recovery requirements
- Acceptance criteria
- Implementation checklist

This README is the human-friendly overview; the v1.1 SRS remains the implementation reference.

---

# License

Add the project's chosen license before publishing the repository.
