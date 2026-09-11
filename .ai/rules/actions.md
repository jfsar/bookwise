---
paths:
  - 'app/Actions/**'
---

# Actions

## Business logic lives in Action classes, not controllers
Use Action classes in `app/Actions/` for domain operations. Controllers orchestrate requests only. Each Action defines authorization, input contract, transaction boundary, invariants, side effects, and audit event. Invoked from controllers with the real signature `handle(...)` (not `run(array $data, ...)` — passing models as the first argument breaks at runtime). Name actions after the operation (e.g. `PostJournalEntry`, `CreateCustomerInvoice`).
