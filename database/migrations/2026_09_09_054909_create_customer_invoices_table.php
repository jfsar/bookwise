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
        Schema::create('customer_invoices', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('invoice_number', 50);
            $table->date('invoice_date');
            $table->date('due_date');
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('exchange_rate', 24, 10)->default(1);
            $table->foreignId('base_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('subtotal', 20, 4)->default(0);
            $table->decimal('discount_amount', 20, 4)->default(0);
            $table->decimal('tax_amount', 20, 4)->default(0);
            $table->decimal('total_amount', 20, 4)->default(0);
            $table->decimal('paid_amount', 20, 4)->default(0);
            $table->decimal('balance_due', 20, 4)->default(0);
            $table->string('status', 30)->default('draft');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->string('idempotency_key', 255)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'invoice_number']);
            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'idempotency_key']);

            $table->index(['organization_id', 'customer_id', 'due_date', 'status'], 'idx_invoice_org_customer_due');
            $table->index(['organization_id', 'status', 'due_date'], 'idx_invoice_org_status_due');

            $table->foreign(['organization_id', 'customer_id'])
                ->references(['organization_id', 'id'])
                ->on('customers')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'journal_entry_id'])
                ->references(['organization_id', 'id'])
                ->on('journal_entries')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE customer_invoices ADD CONSTRAINT ck_customer_invoices_date_order CHECK (invoice_date <= due_date)');
        DB::statement('ALTER TABLE customer_invoices ADD CONSTRAINT ck_customer_invoices_subtotal CHECK (subtotal >= 0)');
        DB::statement('ALTER TABLE customer_invoices ADD CONSTRAINT ck_customer_invoices_discount_amount CHECK (discount_amount >= 0)');
        DB::statement('ALTER TABLE customer_invoices ADD CONSTRAINT ck_customer_invoices_tax_amount CHECK (tax_amount >= 0)');
        DB::statement('ALTER TABLE customer_invoices ADD CONSTRAINT ck_customer_invoices_total_amount CHECK (total_amount >= 0)');
        DB::statement('ALTER TABLE customer_invoices ADD CONSTRAINT ck_customer_invoices_paid_amount CHECK (paid_amount >= 0)');
        DB::statement('ALTER TABLE customer_invoices ADD CONSTRAINT ck_customer_invoices_balance_due CHECK (balance_due >= 0)');
        DB::statement('ALTER TABLE customer_invoices ADD CONSTRAINT ck_customer_invoices_status CHECK (status IN (\'draft\', \'issued\', \'posted\', \'partially_paid\', \'paid\', \'voided\'))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_invoices');
    }
};
