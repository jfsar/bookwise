---
paths:
  - 'app/**'
---

# App

## Organization-scoped multi-tenancy is enforced at every layer
Every organization-owned query and mutation must establish organization context explicitly. Do not trust organization identifiers from the browser. Determine the active organization from authenticated membership and validate any requested organization switch. All org-scoped FKs use composite `(organization_id, parent_id)` referencing `parent(organization_id, id)`.

## Modular monolith: no microservices, strong transactional consistency over decomposition
The system is a modular monolith. Do not split into microservices. Strong transactional consistency is a higher priority than service decomposition. Organize code by domain within the app/ directory.

## Posted financial records are immutable: corrections via reversal only
A posted financial fact is never updated in place. Corrections are represented as new reversal transactions. The single posting boundary (`PostJournalEntry`) is the only path to posting. All financial posting must pass through this domain-level operation.
