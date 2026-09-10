<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->foreign(['organization_id', 'bank_account_id'], 'fk_cust_pay_org_bank')
                ->references(['organization_id', 'id'])
                ->on('bank_accounts')
                ->restrictOnDelete();
        });

        Schema::table('vendor_payments', function (Blueprint $table) {
            $table->foreign(['organization_id', 'bank_account_id'], 'fk_vendor_pay_org_bank')
                ->references(['organization_id', 'id'])
                ->on('bank_accounts')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->dropForeign('fk_cust_pay_org_bank');
        });

        Schema::table('vendor_payments', function (Blueprint $table) {
            $table->dropForeign('fk_vendor_pay_org_bank');
        });
    }
};
