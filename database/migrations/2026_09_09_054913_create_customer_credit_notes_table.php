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
        Schema::create('customer_credit_notes', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('customer_invoices')->restrictOnDelete();
            $table->string('credit_note_number', 50);
            $table->date('date');
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('exchange_rate', 24, 10)->default(1);
            $table->decimal('subtotal', 20, 4)->default(0);
            $table->decimal('tax_amount', 20, 4)->default(0);
            $table->decimal('total_amount', 20, 4)->default(0);
            $table->text('reason');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->string('status', 30)->default('draft');
            $table->timestamp('posted_at')->nullable();
            $table->string('idempotency_key', 255)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'credit_note_number']);
            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'idempotency_key']);

            $table->foreign(['organization_id', 'customer_id'])
                ->references(['organization_id', 'id'])
                ->on('customers')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'invoice_id'])
                ->references(['organization_id', 'id'])
                ->on('customer_invoices')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'journal_entry_id'])
                ->references(['organization_id', 'id'])
                ->on('journal_entries')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE customer_credit_notes ADD CONSTRAINT ck_customer_credit_notes_subtotal CHECK (subtotal >= 0)');
        DB::statement('ALTER TABLE customer_credit_notes ADD CONSTRAINT ck_customer_credit_notes_tax_amount CHECK (tax_amount >= 0)');
        DB::statement('ALTER TABLE customer_credit_notes ADD CONSTRAINT ck_customer_credit_notes_total_amount CHECK (total_amount >= 0)');
        DB::statement('ALTER TABLE customer_credit_notes ADD CONSTRAINT ck_customer_credit_notes_status CHECK (status IN (\'draft\', \'posted\', \'used\', \'voided\'))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_credit_notes');
    }
};
