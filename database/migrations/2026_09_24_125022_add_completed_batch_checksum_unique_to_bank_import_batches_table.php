<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * V1.1 §0.26 idempotency: a completed import is the idempotency unit for
     * bank imports. At most one completed batch may exist per
     * (organization, bank account, file checksum), so retrying the same file
     * returns the original logical result instead of a new batch that would
     * re-import rows. Failed and in-flight statuses are excluded so a corrected
     * file can be retried. Row-level dedup still uses `transaction_hash`.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX uq_import_batch_completed_file ON bank_import_batches (organization_id, bank_account_id, file_checksum) WHERE status = \'completed\' AND file_checksum IS NOT NULL'
        );

        DB::statement(
            'CREATE UNIQUE INDEX uq_bank_tx_org_account_hash ON bank_transactions (organization_id, bank_account_id, transaction_hash) WHERE transaction_hash IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_bank_tx_org_account_hash');
        DB::statement('DROP INDEX IF EXISTS uq_import_batch_completed_file');
    }
};
