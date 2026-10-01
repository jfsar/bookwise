<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * DDL statements are autocommitted one by one (the Neon pooler aborts
     * multi-statement transactions), so ordering must stay dependency-first.
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE recurring_journals ADD CONSTRAINT ck_recurring_journals_status CHECK (status IN ('active', 'paused', 'completed'))");
        DB::statement("ALTER TABLE recurring_journals ADD CONSTRAINT ck_recurring_journals_frequency CHECK (frequency IN ('daily', 'weekly', 'monthly', 'quarterly', 'yearly'))");
        DB::statement('ALTER TABLE recurring_journals ADD CONSTRAINT ck_recurring_journals_end_date CHECK (end_date IS NULL OR end_date >= start_date)');
        DB::statement('ALTER TABLE recurring_journals ADD CONSTRAINT ck_recurring_journals_next_run_date CHECK (next_run_date >= start_date)');
        DB::statement('CREATE INDEX idx_recurring_journals_status_next_run ON recurring_journals (status, next_run_date)');
        DB::statement('ALTER TABLE recurring_journals ADD CONSTRAINT fk_recurring_journals_org_journal FOREIGN KEY (organization_id, journal_id) REFERENCES journals (organization_id, id) ON DELETE RESTRICT');

        DB::statement("ALTER TABLE recurring_journal_executions ADD CONSTRAINT ck_recurring_journal_executions_status CHECK (status IN ('pending', 'completed', 'failed'))");
        DB::statement('ALTER TABLE recurring_journal_executions ADD CONSTRAINT fk_rje_org_journal_entry FOREIGN KEY (organization_id, journal_entry_id) REFERENCES journal_entries (organization_id, id) ON DELETE RESTRICT');
        DB::statement('CREATE INDEX idx_recurring_journal_executions_run_date ON recurring_journal_executions (recurring_journal_id, scheduled_run_date)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_recurring_journal_executions_run_date');
        DB::statement('ALTER TABLE recurring_journal_executions DROP CONSTRAINT IF EXISTS fk_rje_org_journal_entry');
        DB::statement('ALTER TABLE recurring_journal_executions DROP CONSTRAINT IF EXISTS ck_recurring_journal_executions_status');

        DB::statement('DROP INDEX IF EXISTS idx_recurring_journals_status_next_run');
        DB::statement('ALTER TABLE recurring_journals DROP CONSTRAINT IF EXISTS fk_recurring_journals_org_journal');
        DB::statement('ALTER TABLE recurring_journals DROP CONSTRAINT IF EXISTS ck_recurring_journals_next_run_date');
        DB::statement('ALTER TABLE recurring_journals DROP CONSTRAINT IF EXISTS ck_recurring_journals_end_date');
        DB::statement('ALTER TABLE recurring_journals DROP CONSTRAINT IF EXISTS ck_recurring_journals_frequency');
        DB::statement('ALTER TABLE recurring_journals DROP CONSTRAINT IF EXISTS ck_recurring_journals_status');
    }
};
