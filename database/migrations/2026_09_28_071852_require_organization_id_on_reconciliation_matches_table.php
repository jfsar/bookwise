<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * This migration must run outside a transaction: the backfill and the
     * SET NOT NULL are DDL/DML on a table the app is already reading, and on
     * the pooled Neon endpoint a prepared ALTER inside a transaction aborts
     * the server transaction (SQLSTATE 25P02). It also holds an ACCESS
     * EXCLUSIVE lock for the whole backfill if wrapped.
     *
     * The statements are individually idempotent — the UPDATE and DELETE only
     * touch rows where organization_id IS NULL — so a mid-migration failure
     * leaves recoverable partial state rather than a corrupt one.
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     *
     * INV-008 / tenant consistency: reconciliation_matches is an organization-owned
     * table, so organization_id must be NOT NULL. While it was nullable, the three
     * composite foreign keys (fk_recon_match_org_recon, fk_recon_match_org_bank_tx,
     * fk_recon_match_org_journal_line) were never enforced, because a NULL key
     * satisfies any MATCH SIMPLE foreign key. That let a match reference another
     * organization's reconciliation, bank transaction, or journal entry line.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE reconciliation_matches AS m
            SET organization_id = r.organization_id
            FROM bank_reconciliations AS r
            WHERE m.organization_id IS NULL
              AND r.id = m.reconciliation_id
        SQL);

        DB::statement(<<<'SQL'
            DELETE FROM reconciliation_matches
            WHERE organization_id IS NULL
        SQL);

        Schema::table('reconciliation_matches', function (Blueprint $table) {
            $table->bigInteger('organization_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reconciliation_matches', function (Blueprint $table) {
            $table->bigInteger('organization_id')->nullable()->change();
        });
    }
};
