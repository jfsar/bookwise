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
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('cash_account_id')->nullable()->after('bank_account_id');

            $table->foreign(['organization_id', 'cash_account_id'], 'fk_cust_pay_org_cash_acct')
                ->references(['organization_id', 'id'])
                ->on('accounts')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE customer_payments ADD CONSTRAINT ck_customer_payments_debit_source CHECK (status <> \'posted\' OR cash_account_id IS NOT NULL OR bank_account_id IS NOT NULL)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->dropForeign('fk_cust_pay_org_cash_acct');
            $table->dropColumn('cash_account_id');
        });

        DB::statement('ALTER TABLE customer_payments DROP CONSTRAINT IF EXISTS ck_customer_payments_debit_source');
    }
};
