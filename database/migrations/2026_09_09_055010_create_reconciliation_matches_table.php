<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reconciliation_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->restrictOnDelete();
            $table->foreignId('reconciliation_id')->constrained('bank_reconciliations')->restrictOnDelete();
            $table->foreignId('bank_transaction_id')->constrained('bank_transactions')->restrictOnDelete();
            $table->foreignId('journal_entry_line_id')->nullable()->constrained('journal_entry_lines')->restrictOnDelete();
            $table->decimal('matched_amount', 20, 4);
            $table->string('match_type', 30);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'id'], 'uq_recon_match_org_id');

            $table->foreign(['organization_id', 'reconciliation_id'], 'fk_recon_match_org_recon')
                ->references(['organization_id', 'id'])
                ->on('bank_reconciliations')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'bank_transaction_id'], 'fk_recon_match_org_bank_tx')
                ->references(['organization_id', 'id'])
                ->on('bank_transactions')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'journal_entry_line_id'], 'fk_recon_match_org_journal_line')
                ->references(['organization_id', 'id'])
                ->on('journal_entry_lines')
                ->restrictOnDelete();

            $table->index(['organization_id', 'reconciliation_id'], 'idx_recon_match_org_recon');
            $table->index(['organization_id', 'bank_transaction_id'], 'idx_recon_match_org_bank_tx');
            $table->index(['organization_id', 'journal_entry_line_id'], 'idx_recon_match_org_journal_line');
        });

        DB::statement('ALTER TABLE reconciliation_matches ADD CONSTRAINT ck_recon_match_amount CHECK (matched_amount > 0)');
        DB::statement('ALTER TABLE reconciliation_matches ADD CONSTRAINT ck_recon_match_type CHECK (match_type IN (\'exact\', \'partial\', \'split\', \'combined\'))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconciliation_matches');
    }
};
