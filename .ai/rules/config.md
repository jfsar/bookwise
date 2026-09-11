---
paths:
  - config/database.php
---

# Config

## pgsql_direct falls back to DB_HOST on Wasmer
pgsql_direct reads DB_HOST_DIRECT with a fallback to DB_HOST (added for Wasmer Edge, which has no Neon-style pooler/direct split). Wasmer deploys use only the regular pgsql connection; migrations run via the default connection there.
