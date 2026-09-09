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
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->date('statement_date');
            $table->date('statement_start_date');
            $table->date('statement_end_date');
            $table->decimal('opening_balance', 20, 4);
            $table->decimal('closing_balance', 20, 4);
            $table->decimal('reconciled_balance', 20, 4)->default(0);
            $table->decimal('difference', 20, 4)->default(0);
            $table->string('status', 30)->default('in_progress');
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('finalization_reason')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'id']);

            $table->foreign(['organization_id', 'bank_account_id'])
                ->references(['organization_id', 'id'])
                ->on('bank_accounts')
                ->restrictOnDelete();

            $table->index(['organization_id', 'bank_account_id', 'status'], 'idx_recon_org_account_status');
            $table->index(['organization_id', 'status', 'statement_date'], 'idx_recon_org_status_date');
        });

        DB::statement('ALTER TABLE bank_reconciliations ADD CONSTRAINT ck_recon_date_order CHECK (statement_start_date <= statement_end_date)');
        DB::statement('ALTER TABLE bank_reconciliations ADD CONSTRAINT ck_recon_status CHECK (status IN (\'in_progress\', \'finalized\', \'reopened\'))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliations');
    }
};
