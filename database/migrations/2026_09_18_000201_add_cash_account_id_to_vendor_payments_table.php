<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vendor_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('cash_account_id')->nullable()->after('bank_account_id');

            $table->foreign(['organization_id', 'cash_account_id'], 'fk_vpay_org_cash_acct')
                ->references(['organization_id', 'id'])
                ->on('accounts')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE vendor_payments ADD CONSTRAINT ck_vendor_payments_debit_source CHECK (status <> \'posted\' OR cash_account_id IS NOT NULL OR bank_account_id IS NOT NULL)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_payments', function (Blueprint $table) {
            $table->dropForeign('fk_vpay_org_cash_acct');
            $table->dropColumn('cash_account_id');
        });

        DB::statement('ALTER TABLE vendor_payments DROP CONSTRAINT IF EXISTS ck_vendor_payments_debit_source');
    }
};
