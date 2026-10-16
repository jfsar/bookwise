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
        Schema::create('account_daily_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->unsignedBigInteger('account_id');
            $table->date('balance_date');
            $table->decimal('debit_total', 20, 4)->default(0);
            $table->decimal('credit_total', 20, 4)->default(0);
            $table->decimal('net_change', 20, 4)->default(0);
            $table->decimal('closing_balance', 20, 4)->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'id'], 'uq_daily_balances_org_id');
            $table->unique(['organization_id', 'account_id', 'balance_date'], 'uq_daily_balances_org_account_date');
            $table->index(['organization_id', 'balance_date'], 'idx_daily_balances_org_date');

            $table->foreign(['organization_id', 'account_id'], 'fk_daily_balances_account')
                ->references(['organization_id', 'id'])
                ->on('accounts')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_daily_balances');
    }
};
