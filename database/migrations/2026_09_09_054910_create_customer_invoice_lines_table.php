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
        Schema::create('customer_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('customer_invoices')->restrictOnDelete();
            $table->integer('line_number');
            $table->text('description');
            $table->decimal('quantity', 20, 4)->default(1);
            $table->decimal('unit_price', 20, 4);
            $table->decimal('discount_amount', 20, 4)->default(0);
            $table->decimal('tax_amount', 20, 4)->default(0);
            $table->decimal('line_total', 20, 4)->default(0);
            $table->foreignId('revenue_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('tax_code_id')->nullable()->constrained('tax_codes')->restrictOnDelete();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->restrictOnDelete();
            $table->decimal('applied_tax_rate', 10, 6)->nullable();
            $table->timestamps();

            $table->unique(['invoice_id', 'line_number']);

            $table->foreign(['organization_id', 'invoice_id'])
                ->references(['organization_id', 'id'])
                ->on('customer_invoices')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'revenue_account_id'])
                ->references(['organization_id', 'id'])
                ->on('accounts')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'tax_code_id'])
                ->references(['organization_id', 'id'])
                ->on('tax_codes')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE customer_invoice_lines ADD CONSTRAINT ck_customer_invoice_lines_quantity CHECK (quantity >= 0)');
        DB::statement('ALTER TABLE customer_invoice_lines ADD CONSTRAINT ck_customer_invoice_lines_unit_price CHECK (unit_price >= 0)');
        DB::statement('ALTER TABLE customer_invoice_lines ADD CONSTRAINT ck_customer_invoice_lines_discount_amount CHECK (discount_amount >= 0)');
        DB::statement('ALTER TABLE customer_invoice_lines ADD CONSTRAINT ck_customer_invoice_lines_tax_amount CHECK (tax_amount >= 0)');
        DB::statement('ALTER TABLE customer_invoice_lines ADD CONSTRAINT ck_customer_invoice_lines_line_total CHECK (line_total >= 0)');
        DB::statement('ALTER TABLE customer_invoice_lines ADD CONSTRAINT ck_customer_invoice_lines_applied_tax_rate CHECK (applied_tax_rate IS NULL OR (applied_tax_rate >= 0 AND applied_tax_rate <= 100))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_invoice_lines');
    }
};
