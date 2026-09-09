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
        Schema::create('recurring_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('recurring_journal_id')->constrained('recurring_journals')->restrictOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->integer('line_number');
            $table->text('description')->nullable();
            $table->decimal('debit', 20, 4)->default(0);
            $table->decimal('credit', 20, 4)->default(0);
            $table->timestamps();

            $table->unique(['recurring_journal_id', 'line_number']);

            $table->foreign(['organization_id', 'recurring_journal_id'])
                ->references(['organization_id', 'id'])
                ->on('recurring_journals')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'account_id'])
                ->references(['organization_id', 'id'])
                ->on('accounts')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE recurring_journal_lines ADD CONSTRAINT ck_recurring_journal_lines_debit_nonnegative CHECK (debit >= 0)');
        DB::statement('ALTER TABLE recurring_journal_lines ADD CONSTRAINT ck_recurring_journal_lines_credit_nonnegative CHECK (credit >= 0)');
        DB::statement('ALTER TABLE recurring_journal_lines ADD CONSTRAINT ck_recurring_journal_lines_exclusive CHECK (NOT (debit > 0 AND credit > 0))');
        DB::statement('ALTER TABLE recurring_journal_lines ADD CONSTRAINT ck_recurring_journal_lines_positive CHECK (debit > 0 OR credit > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_journal_lines');
    }
};
