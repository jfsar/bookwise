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
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->date('transaction_date');
            $table->date('value_date')->nullable();
            $table->string('reference', 200)->nullable();
            $table->text('description')->nullable();
            $table->decimal('debit', 20, 4)->default(0);
            $table->decimal('credit', 20, 4)->default(0);
            $table->decimal('balance', 20, 4)->nullable();
            $table->string('external_reference', 200)->nullable();
            $table->string('external_transaction_id', 255)->nullable();
            $table->unsignedBigInteger('import_batch_id')->nullable();
            $table->string('transaction_hash', 255)->nullable();
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->string('source', 50)->default('manual');
            $table->string('reconciliation_status', 30)->default('unreconciled');
            $table->timestamps();

            $table->unique(['organization_id', 'id']);

            $table->foreign(['organization_id', 'bank_account_id'])
                ->references(['organization_id', 'id'])
                ->on('bank_accounts')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'import_batch_id'], 'fk_bank_tx_org_import_batch')
                ->references(['organization_id', 'id'])
                ->on('bank_import_batches')
                ->restrictOnDelete();

            $table->index(['organization_id', 'bank_account_id', 'transaction_date', 'id'], 'idx_bank_tx_org_account_date');
            $table->index(['organization_id', 'reconciliation_status'], 'idx_bank_tx_org_recon_status');
            $table->index(['transaction_hash'], 'idx_bank_tx_hash');
        });

        DB::statement('ALTER TABLE bank_transactions ADD CONSTRAINT ck_bank_tx_debit CHECK (debit >= 0)');
        DB::statement('ALTER TABLE bank_transactions ADD CONSTRAINT ck_bank_tx_credit CHECK (credit >= 0)');
        DB::statement('ALTER TABLE bank_transactions ADD CONSTRAINT ck_bank_tx_exclusive CHECK (NOT (debit > 0 AND credit > 0))');
        DB::statement('ALTER TABLE bank_transactions ADD CONSTRAINT ck_bank_tx_non_zero CHECK (debit > 0 OR credit > 0)');
        DB::statement('ALTER TABLE bank_transactions ADD CONSTRAINT ck_bank_tx_source CHECK (source IN (\'manual\', \'import\', \'system\'))');
        DB::statement('ALTER TABLE bank_transactions ADD CONSTRAINT ck_bank_tx_recon_status CHECK (reconciliation_status IN (\'unreconciled\', \'matched\', \'reconciled\'))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
