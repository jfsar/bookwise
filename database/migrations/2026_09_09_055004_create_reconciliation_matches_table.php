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
            $table->foreignId('reconciliation_id')->constrained('bank_reconciliations')->restrictOnDelete();
            $table->foreignId('bank_transaction_id')->constrained('bank_transactions')->restrictOnDelete();
            $table->foreignId('journal_entry_line_id')->nullable()->constrained('journal_entry_lines')->restrictOnDelete();
            $table->decimal('matched_amount', 20, 4);
            $table->string('match_type', 30);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['reconciliation_id'], 'idx_recon_match_recon');
            $table->index(['bank_transaction_id'], 'idx_recon_match_bank_tx');
            $table->index(['journal_entry_line_id'], 'idx_recon_match_journal_line');
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
