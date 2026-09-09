<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->unique(['organization_id', 'id'], 'uq_journal_entry_lines_org_id');
        });

        Schema::table('bank_import_batches', function (Blueprint $table) {
            $table->unique(['organization_id', 'id'], 'uq_import_batch_org_id');
        });

        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->foreign(['organization_id', 'import_batch_id'], 'fk_bank_tx_org_import_batch')
                ->references(['organization_id', 'id'])
                ->on('bank_import_batches')
                ->restrictOnDelete();
        });

        Schema::table('reconciliation_matches', function (Blueprint $table) {
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();

            $table->dropForeign(['reconciliation_id']);
            $table->dropForeign(['bank_transaction_id']);
            $table->dropForeign(['journal_entry_line_id']);

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

            $table->dropIndex('idx_recon_match_recon');
            $table->dropIndex('idx_recon_match_bank_tx');
            $table->dropIndex('idx_recon_match_journal_line');

            $table->index(['organization_id', 'reconciliation_id'], 'idx_recon_match_org_recon');
            $table->index(['organization_id', 'bank_transaction_id'], 'idx_recon_match_org_bank_tx');
            $table->index(['organization_id', 'journal_entry_line_id'], 'idx_recon_match_org_journal_line');
        });

        Schema::table('customer_payments', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);

            $table->foreign(['organization_id', 'bank_account_id'], 'fk_cust_pay_org_bank')
                ->references(['organization_id', 'id'])
                ->on('bank_accounts')
                ->restrictOnDelete();
        });

        Schema::table('vendor_payments', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);

            $table->foreign(['organization_id', 'bank_account_id'], 'fk_vendor_pay_org_bank')
                ->references(['organization_id', 'id'])
                ->on('bank_accounts')
                ->restrictOnDelete();
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->restrictOnDelete();

            $table->index(['organization_id'], 'idx_notifications_org');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifications_org');
            $table->dropConstrainedForeignId('organization_id');
        });

        Schema::table('vendor_payments', function (Blueprint $table) {
            $table->dropForeign('fk_vendor_pay_org_bank');

            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->restrictOnDelete();
        });

        Schema::table('customer_payments', function (Blueprint $table) {
            $table->dropForeign('fk_cust_pay_org_bank');

            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->restrictOnDelete();
        });

        Schema::table('reconciliation_matches', function (Blueprint $table) {
            $table->dropForeign('fk_recon_match_org_recon');
            $table->dropForeign('fk_recon_match_org_bank_tx');
            $table->dropForeign('fk_recon_match_org_journal_line');

            $table->dropIndex('idx_recon_match_org_recon');
            $table->dropIndex('idx_recon_match_org_bank_tx');
            $table->dropIndex('idx_recon_match_org_journal_line');

            $table->foreign('reconciliation_id')->references('id')->on('bank_reconciliations')->restrictOnDelete();
            $table->foreign('bank_transaction_id')->references('id')->on('bank_transactions')->restrictOnDelete();
            $table->foreign('journal_entry_line_id')->references('id')->on('journal_entry_lines')->restrictOnDelete();

            $table->index(['reconciliation_id'], 'idx_recon_match_recon');
            $table->index(['bank_transaction_id'], 'idx_recon_match_bank_tx');
            $table->index(['journal_entry_line_id'], 'idx_recon_match_journal_line');

            $table->dropConstrainedForeignId('organization_id');
        });

        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropForeign('fk_bank_tx_org_import_batch');
        });

        Schema::table('bank_import_batches', function (Blueprint $table) {
            $table->dropUnique('uq_import_batch_org_id');
        });

        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->dropUnique('uq_journal_entry_lines_org_id');
        });
    }
};
