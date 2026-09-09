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
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('vendor_number', 50);
            $table->string('name', 200);
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('tax_number', 100)->nullable();
            $table->integer('payment_terms_days')->default(0);
            $table->foreignId('payable_account_id')->constrained('accounts')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('active');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'vendor_number']);
            $table->unique(['organization_id', 'id']);

            $table->foreign(['organization_id', 'payable_account_id'])
                ->references(['organization_id', 'id'])
                ->on('accounts')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE vendors ADD CONSTRAINT ck_vendors_payment_terms_days CHECK (payment_terms_days >= 0)');
        DB::statement('ALTER TABLE vendors ADD CONSTRAINT ck_vendors_status CHECK (status IN (\'active\', \'inactive\', \'archived\'))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
