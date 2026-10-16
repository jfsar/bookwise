# Backup & Disaster Recovery

Bookwise runs in two pieces that must both survive a disaster: the PostgreSQL
database (all accounting data) and object storage (attachments). This document
is the deployment plan required by SRS §0.31 and is kept in sync with the
automated workflows in `.github/workflows/`.

A backup that has never been restored successfully is not a verified backup.

## Objectives

| Objective | Target |
| --- | --- |
| RPO (max data loss) | 24 hours |
| RTO (max downtime) | 4 hours |
| Backup frequency | Daily, 03:00 UTC |
| Retention | 30 days of daily backups |
| Restore test frequency | Weekly, Sundays 04:00 UTC |
| Offsite storage | Cloudflare R2, independent of the database host |

## Database backups

`.github/workflows/backup.yml` runs daily and on demand. It:

1. Dumps production via `DB_HOST_DIRECT` (bypassing the connection pooler) with a
   version-matched `postgres:18` client, in PostgreSQL custom format.
2. Encrypts the dump with AES-256 (`gpg --symmetric`) using the
   `BACKUP_ENCRYPTION_KEY` Actions secret before it leaves the runner.
3. Uploads `db-backups/bookwise-<UTC timestamp>.dump.gpg` to the Cloudflare R2
   bucket (`AWS_*` secrets).
4. Prunes objects older than 30 days.

Encryption is applied on the runner, so the object never exists unencrypted in
remote storage. The passphrase is only stored in Actions secrets.

## Restore verification

`.github/workflows/restore-verify.yml` runs weekly and on demand. It is the only
thing that makes a backup "verified". It:

1. Downloads the most recent encrypted backup from R2.
2. Decrypts it and restores it into a throwaway `postgres:18` service container.
3. Runs `php artisan db:verify-restore` against the restored database.

`db:verify-restore` (`App\Services\DataIntegrityVerifier`) fails the run unless
every check passes:

- **migrations** — the restored schema is not behind the checked-out code.
- **audit_chain** — every organization's tamper-evident audit hash chain still
  validates (no silent edits in the backup).
- **posted_entries_balanced** — no posted journal entry has
  `total_debit <> total_credit`.
- **trial_balance** — posted journal lines reconcile per organization
  (debits = credits).

The workflow points both `DB_HOST` and `DB_HOST_DIRECT` at the scratch database
because `AuditLogService` reads through `pgsql_direct`.

## Manual restore runbook

To restore production from a backup:

1. Create an empty database on the target PostgreSQL server.
2. Download the desired backup from `s3://<bucket>/db-backups/` (R2 endpoint).
3. Decrypt: `gpg --decrypt --output restore.dump bookwise-<timestamp>.dump.gpg`.
4. Restore: `pg_restore --no-owner --no-privileges --exit-on-error -d <db> restore.dump`.
5. Point `DB_HOST` (and `DB_HOST_DIRECT`) at the restored database.
6. Verify: `php artisan db:verify-restore`. Do not cut traffic over until it passes.
7. If the backup predates the current release, run `php artisan migrate --force`
   before verifying.

## Attachments

Attachments are stored on Cloudflare R2 (`s3` disk), not in PostgreSQL, so they
need their own durability strategy. Enable object versioning on the bucket and
rely on R2's cross-region replication for offsite durability. Backup and restore
of the database does not cover attachments; treat R2 as the source of truth for
files and the database only for metadata.

## Required secrets

| Secret | Purpose |
| --- | --- |
| `DB_HOST_DIRECT`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Direct database access for `pg_dump`. |
| `BACKUP_ENCRYPTION_KEY` | Symmetric passphrase used to encrypt and decrypt backups. |
| `AWS_BUCKET`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_ENDPOINT` | Cloudflare R2 access for backup storage. |
