<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reporting_fact_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->unsignedBigInteger('journal_entry_id');
            $table->unsignedBigInteger('journal_entry_line_id');
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('journal_id')->nullable();
            $table->unsignedBigInteger('fiscal_year_id')->nullable();
            $table->unsignedBigInteger('accounting_period_id')->nullable();
            $table->date('entry_date');
            $table->string('entry_number', 50);
            $table->string('account_code', 50);
            $table->decimal('debit_amount', 20, 4)->default(0);
            $table->decimal('credit_amount', 20, 4)->default(0);
            $table->decimal('base_amount', 20, 4)->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'id'], 'uq_reporting_facts_org_id');
            $table->unique(['organization_id', 'journal_entry_line_id'], 'uq_reporting_facts_line');
            $table->index(['organization_id', 'entry_date'], 'idx_reporting_facts_org_date');
            $table->index(['organization_id', 'account_id', 'entry_date'], 'idx_reporting_facts_org_account_date');

            $table->foreign(['organization_id', 'journal_entry_id'], 'fk_reporting_facts_org_entry')
                ->references(['organization_id', 'id'])
                ->on('journal_entries')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'journal_entry_line_id'], 'fk_reporting_facts_org_line')
                ->references(['organization_id', 'id'])
                ->on('journal_entry_lines')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'account_id'], 'fk_reporting_facts_org_account')
                ->references(['organization_id', 'id'])
                ->on('accounts')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'journal_id'], 'fk_reporting_facts_org_journal')
                ->references(['organization_id', 'id'])
                ->on('journals')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'fiscal_year_id'], 'fk_reporting_facts_org_fiscal_year')
                ->references(['organization_id', 'id'])
                ->on('fiscal_years')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'accounting_period_id'], 'fk_reporting_facts_org_period')
                ->references(['organization_id', 'id'])
                ->on('accounting_periods')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reporting_fact_tables');
    }
};
