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
        Schema::create('vendor_bills', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->string('bill_number', 50);
            $table->string('vendor_reference', 100)->nullable();
            $table->date('bill_date');
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
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->string('idempotency_key', 255)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'bill_number']);
            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'idempotency_key']);

            $table->index(['organization_id', 'vendor_id', 'due_date', 'status'], 'idx_bill_org_vendor_due');
            $table->index(['organization_id', 'status', 'due_date'], 'idx_bill_org_status_due');

            $table->foreign(['organization_id', 'vendor_id'])
                ->references(['organization_id', 'id'])
                ->on('vendors')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'journal_entry_id'])
                ->references(['organization_id', 'id'])
                ->on('journal_entries')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE vendor_bills ADD CONSTRAINT ck_vendor_bills_date_order CHECK (bill_date <= due_date)');
        DB::statement('ALTER TABLE vendor_bills ADD CONSTRAINT ck_vendor_bills_subtotal CHECK (subtotal >= 0)');
        DB::statement('ALTER TABLE vendor_bills ADD CONSTRAINT ck_vendor_bills_discount_amount CHECK (discount_amount >= 0)');
        DB::statement('ALTER TABLE vendor_bills ADD CONSTRAINT ck_vendor_bills_tax_amount CHECK (tax_amount >= 0)');
        DB::statement('ALTER TABLE vendor_bills ADD CONSTRAINT ck_vendor_bills_total_amount CHECK (total_amount >= 0)');
        DB::statement('ALTER TABLE vendor_bills ADD CONSTRAINT ck_vendor_bills_paid_amount CHECK (paid_amount >= 0)');
        DB::statement('ALTER TABLE vendor_bills ADD CONSTRAINT ck_vendor_bills_balance_due CHECK (balance_due >= 0)');
        DB::statement('ALTER TABLE vendor_bills ADD CONSTRAINT ck_vendor_bills_status CHECK (status IN (\'draft\', \'posted\', \'partially_paid\', \'paid\', \'voided\'))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_bills');
    }
};
