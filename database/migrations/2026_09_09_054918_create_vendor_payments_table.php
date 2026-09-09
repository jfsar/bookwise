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
        Schema::create('vendor_payments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->string('payment_number', 50);
            $table->date('payment_date');
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('exchange_rate', 24, 10)->default(1);
            $table->foreignId('base_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('amount', 20, 4);
            $table->string('payment_method', 50);
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->string('reference', 100)->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->string('idempotency_key', 255)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'payment_number']);
            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'idempotency_key']);

            $table->index(['organization_id', 'vendor_id', 'payment_date', 'status'], 'idx_vpay_org_vendor_date');

            $table->foreign(['organization_id', 'vendor_id'])
                ->references(['organization_id', 'id'])
                ->on('vendors')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'journal_entry_id'])
                ->references(['organization_id', 'id'])
                ->on('journal_entries')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE vendor_payments ADD CONSTRAINT ck_vendor_payments_amount CHECK (amount > 0)');
        DB::statement('ALTER TABLE vendor_payments ADD CONSTRAINT ck_vendor_payments_status CHECK (status IN (\'draft\', \'posted\', \'voided\'))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_payments');
    }
};
