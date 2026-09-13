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
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('cash_flow_section', 30)->nullable();
            $table->boolean('is_cash_account')->default(false);
        });

        DB::statement("ALTER TABLE accounts ADD CONSTRAINT ck_accounts_cash_flow_section CHECK (cash_flow_section IS NULL OR cash_flow_section IN ('operating', 'investing', 'financing'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE accounts DROP CONSTRAINT IF EXISTS ck_accounts_cash_flow_section');

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['cash_flow_section', 'is_cash_account']);
        });
    }
};
