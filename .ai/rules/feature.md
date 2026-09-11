---
paths:
  - 'tests/Feature/**'
---

# Feature

## Controllers are exercised over HTTP, not Livewire::test
Feature tests post to controller routes (e.g. `/journal-entries/{id}/submit`) and assert status/redirect/session rather than component state. Set up org membership via `user->organizations()->attach($org->id, ['membership_status' => 'active'])` and grant permissions via `$role->permissions()->attach($permission)`; workflows return 403 for unauthorized users.
