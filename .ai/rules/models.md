---
paths:
  - 'app/Models/**'
---

# Models

## ULID identifiers via HasUlidIdentifier, not HasUlids
Models with a NOT NULL `ulid` column use the `HasUlidIdentifier` trait (app/Models/Concerns), which auto-fills ulid on `creating` WITHOUT touching the key type. Do NOT use Laravel's `HasUlids` trait: it sets keyType='string'/incrementing=false, breaks the bigint `id` PK and FK references (e.g. passkeys_user_id_foreign datatype mismatch).
