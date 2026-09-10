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
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->integer('line_number');
            $table->text('description')->nullable();
            $table->decimal('debit', 20, 4)->default(0);
            $table->decimal('credit', 20, 4)->default(0);
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('currency_amount', 20, 4)->nullable();
            $table->decimal('base_amount', 20, 4);
            $table->decimal('exchange_rate', 24, 10)->default(1);
            $table->string('reference', 100)->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'id'], 'uq_journal_entry_lines_org_id');
            $table->unique(['journal_entry_id', 'line_number']);

            $table->foreign(['organization_id', 'journal_entry_id'])
                ->references(['organization_id', 'id'])
                ->on('journal_entries')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'account_id'])
                ->references(['organization_id', 'id'])
                ->on('accounts')
                ->restrictOnDelete();

            $table->index(['account_id', 'journal_entry_id', 'line_number'], 'idx_jel_account_entry');
            $table->index(['organization_id', 'account_id', 'journal_entry_id'], 'idx_jel_org_account_entry');
        });

        DB::statement('ALTER TABLE journal_entry_lines ADD CONSTRAINT ck_journal_entry_lines_debit_nonnegative CHECK (debit >= 0)');
        DB::statement('ALTER TABLE journal_entry_lines ADD CONSTRAINT ck_journal_entry_lines_credit_nonnegative CHECK (credit >= 0)');
        DB::statement('ALTER TABLE journal_entry_lines ADD CONSTRAINT ck_journal_entry_lines_exclusive CHECK (NOT (debit > 0 AND credit > 0))');
        DB::statement('ALTER TABLE journal_entry_lines ADD CONSTRAINT ck_journal_entry_lines_positive CHECK (debit > 0 OR credit > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
    }
};
