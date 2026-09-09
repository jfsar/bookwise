<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->foreign('bank_account_id')
                ->references('id')
                ->on('bank_accounts')
                ->restrictOnDelete();
        });

        Schema::table('vendor_payments', function (Blueprint $table) {
            $table->foreign('bank_account_id')
                ->references('id')
                ->on('bank_accounts')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
        });

        Schema::table('vendor_payments', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
        });
    }
};
