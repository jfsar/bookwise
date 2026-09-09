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
        Schema::create('customer_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained('customer_payments')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('customer_invoices')->restrictOnDelete();
            $table->decimal('amount', 20, 4);
            $table->timestamp('allocated_at')->useCurrent();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->foreign(['organization_id', 'payment_id'])
                ->references(['organization_id', 'id'])
                ->on('customer_payments')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'invoice_id'])
                ->references(['organization_id', 'id'])
                ->on('customer_invoices')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE customer_payment_allocations ADD CONSTRAINT ck_customer_payment_allocations_amount CHECK (amount > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_payment_allocations');
    }
};
