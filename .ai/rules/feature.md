---
paths:
  - 'tests/Feature/**'
---

# Feature

## Livewire components under app/Http/Livewire tested with Livewire::test
Feature tests for JournalEntryActions prove component authorization: unauthorized user gets assertForbidden(), permitted user gets assertDispatched('entry-updated'). Set up org membership via user->organizations()->attach($org->id, ['membership_status' => 'active']) and grant permission via $role->permissions()->attach($permission).
