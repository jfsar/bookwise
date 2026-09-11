---
paths:
  - 'app/Policies/**'
---

# Policies

## Authorization uses Laravel Policies with granular permissions
Enforce authorization via Laravel Policies at route/request, controller action, and domain-operation boundaries. UI visibility is never an authorization control. Permission slugs follow `entity-action` format (e.g. `view-accounts`, `post-journal-entries`). Controllers call `$this->authorize()` (base Controller uses `AuthorizesRequests`); do not gate index/create pages with policies, only workflow mutations.
