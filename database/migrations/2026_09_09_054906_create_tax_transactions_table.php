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
        Schema::create('tax_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('tax_code_id')->constrained('tax_codes')->restrictOnDelete();
            $table->foreignId('tax_rate_id')->constrained('tax_rates')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->restrictOnDelete();
            $table->decimal('taxable_amount', 20, 4);
            $table->decimal('tax_amount', 20, 4);
            $table->decimal('applied_rate', 10, 6);
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->foreignId('base_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('exchange_rate', 24, 10)->default(1);
            $table->date('transaction_date');
            $table->timestamps();

            $table->unique(['organization_id', 'id']);

            $table->foreign(['organization_id', 'tax_code_id'])
                ->references(['organization_id', 'id'])
                ->on('tax_codes')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'tax_rate_id'])
                ->references(['organization_id', 'id'])
                ->on('tax_rates')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'journal_entry_id'])
                ->references(['organization_id', 'id'])
                ->on('journal_entries')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE tax_transactions ADD CONSTRAINT ck_tax_transactions_taxable_amount CHECK (taxable_amount >= 0)');
        DB::statement('ALTER TABLE tax_transactions ADD CONSTRAINT ck_tax_transactions_tax_amount CHECK (tax_amount >= 0)');
        DB::statement('ALTER TABLE tax_transactions ADD CONSTRAINT ck_tax_transactions_applied_rate CHECK (applied_rate >= 0 AND applied_rate <= 100)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_transactions');
    }
};
