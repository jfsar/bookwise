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
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->string('bank_name', 200);
            $table->string('account_name', 200);
            $table->string('account_number', 255)->nullable();
            $table->string('iban', 100)->nullable();
            $table->string('routing_number', 100)->nullable();
            $table->string('masked_account_number', 255)->nullable();
            $table->text('encrypted_account_number')->nullable();
            $table->string('last4', 4)->nullable();
            $table->decimal('opening_balance', 20, 4)->default(0);
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->unique(['organization_id', 'id']);

            $table->foreign(['organization_id', 'account_id'])
                ->references(['organization_id', 'id'])
                ->on('accounts')
                ->restrictOnDelete();

            $table->index(['organization_id', 'status'], 'idx_bank_account_org_status');
        });

        DB::statement('ALTER TABLE bank_accounts ADD CONSTRAINT ck_bank_accounts_opening_balance CHECK (opening_balance >= 0)');
        DB::statement('ALTER TABLE bank_accounts ADD CONSTRAINT ck_bank_accounts_status CHECK (status IN (\'active\', \'inactive\', \'closed\'))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
