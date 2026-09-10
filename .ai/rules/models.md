---
paths:
  - 'app/Models/**'
  - 'app/Models/*.php'
---

# Models

## ULID identifiers via HasUlidIdentifier, not HasUlids
Models with a NOT NULL `ulid` column use the `HasUlidIdentifier` trait (app/Models/Concerns), which auto-fills ulid on `creating` WITHOUT touching the key type. Do NOT use Laravel's `HasUlids` trait: it sets keyType='string'/incrementing=false, breaks the bigint `id` PK and FK references (e.g. passkeys_user_id_foreign datatype mismatch).

## permission_role pivot has full timestamp columns
Role::permissions() uses withTimestamps() and the permission_role table now has both created_at and updated_at (added by add_updated_at_to_permission_role_table). Attach/sync permissions via the relationship ($role->permissions()->attach($permission)), not raw DB::table inserts.
