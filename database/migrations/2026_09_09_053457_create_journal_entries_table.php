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
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('journal_id')->constrained('journals')->restrictOnDelete();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->restrictOnDelete();
            $table->foreignId('accounting_period_id')->constrained('accounting_periods')->restrictOnDelete();
            $table->string('entry_number', 50);
            $table->date('entry_date');
            $table->string('reference', 100)->nullable();
            $table->text('description');
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->foreignId('base_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('exchange_rate', 24, 10)->default(1);
            $table->decimal('total_debit', 20, 4)->default(0);
            $table->decimal('total_credit', 20, 4)->default(0);
            $table->string('status', 30)->default('draft');
            $table->unsignedInteger('posting_version')->default(1);
            $table->string('idempotency_key')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'entry_number']);
            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'idempotency_key']);

            $table->foreign('reversal_of_id')->references('id')->on('journal_entries')->restrictOnDelete();
            $table->foreign(['organization_id', 'journal_id'])
                ->references(['organization_id', 'id'])
                ->on('journals')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'fiscal_year_id'])
                ->references(['organization_id', 'id'])
                ->on('fiscal_years')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'accounting_period_id'])
                ->references(['organization_id', 'id'])
                ->on('accounting_periods')
                ->restrictOnDelete();

            $table->index(['organization_id', 'entry_date', 'id'], 'idx_je_org_date');
            $table->index(['organization_id', 'accounting_period_id', 'entry_date', 'id'], 'idx_je_org_period_date');
            $table->index(['organization_id', 'status', 'entry_date', 'id'], 'idx_je_org_status_date');
        });

        DB::statement('ALTER TABLE journal_entries ADD CONSTRAINT ck_journal_entries_total_debit_nonnegative CHECK (total_debit >= 0)');
        DB::statement('ALTER TABLE journal_entries ADD CONSTRAINT ck_journal_entries_total_credit_nonnegative CHECK (total_credit >= 0)');
        DB::statement("ALTER TABLE journal_entries ADD CONSTRAINT ck_journal_entries_status CHECK (status IN ('draft', 'submitted', 'approved', 'posted', 'rejected', 'cancelled', 'reversed'))");

        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->foreign('close_journal_entry_id')
                ->references('id')
                ->on('journal_entries')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->dropForeign(['close_journal_entry_id']);
        });

        Schema::dropIfExists('journal_entries');
    }
};
