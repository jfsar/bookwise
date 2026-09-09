---
paths:
  - 'app/Actions/**'
---

# Actions

## Business logic lives in Action classes, not controllers or Livewire components
Use Action classes in `app/Actions/` for domain operations. Controllers and Livewire components orchestrate requests only. Each Action defines authorization, input contract, transaction boundary, invariants, side effects, and audit event. Name actions after the operation (e.g. `PostJournalEntry`, `CreateCustomerInvoice`).
