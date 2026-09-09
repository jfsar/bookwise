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
        Schema::create('financial_period_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->restrictOnDelete();
            $table->foreignId('accounting_period_id')->nullable()->constrained('accounting_periods')->restrictOnDelete();
            $table->string('status', 30)->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('initiated_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('closing_journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->decimal('trial_balance_debit', 20, 4)->nullable();
            $table->decimal('trial_balance_credit', 20, 4)->nullable();
            $table->jsonb('validation_report')->nullable();
            $table->text('close_reason')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'id']);

            $table->foreign(['organization_id', 'fiscal_year_id'])
                ->references(['organization_id', 'id'])
                ->on('fiscal_years')
                ->restrictOnDelete();

            $table->foreign(['organization_id', 'accounting_period_id'])
                ->references(['organization_id', 'id'])
                ->on('accounting_periods')
                ->restrictOnDelete();

            $table->foreign(['organization_id', 'closing_journal_entry_id'])
                ->references(['organization_id', 'id'])
                ->on('journal_entries')
                ->restrictOnDelete();

            $table->index(['organization_id', 'status'], 'idx_period_closing_org_status');
        });

        DB::statement('ALTER TABLE financial_period_closings ADD CONSTRAINT ck_period_closing_status CHECK (status IN (\'pending\', \'in_progress\', \'completed\', \'failed\', \'reopened\'))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_period_closings');
    }
};
