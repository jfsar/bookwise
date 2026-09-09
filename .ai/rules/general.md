---
paths:
  - phpunit.xml
  - '**/*.php'
---

# General

## Use pgsql_testing, never sqlite, for tests
Tests run against a dedicated PostgreSQL database via the `pgsql_testing` connection (see config/database.php). It resolves from TEST_DATABASE_URL, else TEST_DB_* vars, else falls back to DB_HOST_TEST/DB_HOST_TEST_PASSWORD/DB_USERNAME/DB_DATABASE. Never switch phpunit to sqlite :memory: — the Phase 0 migrations are PostgreSQL-only (jsonb, ALTER TABLE CHECK, CREATE EXTENSION) and will not run there.

## No BOMs, always open with <?php
On this Windows setup, PowerShell `Set-Content -Encoding UTF8` writes a UTF-8 BOM. A BOM at the top of any PHP file crashes PHPUnit with "Premature end of PHP process". Also, every PHP file must start with `<?php` — a file beginning with `namespace ...` is treated as inline HTML (php -l passes but classes are never declared).
