-- Runs once, while the postgres-data volume is still empty, so local tests
-- never fall back to (and wipe) the development database.
--
-- The name must match TEST_DB_DATABASE in .env / .env.example. Changing it
-- afterwards needs a volume reset: `composer db:reset`.
SELECT 'CREATE DATABASE bookwise_test'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'bookwise_test')\gexec