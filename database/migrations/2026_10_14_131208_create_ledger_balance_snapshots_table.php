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
        Schema::create('ledger_balance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->unsignedBigInteger('account_id');
            $table->date('as_of_date');
            $table->decimal('debit_balance', 20, 4)->default(0);
            $table->decimal('credit_balance', 20, 4)->default(0);
            $table->decimal('net_balance', 20, 4)->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'id'], 'uq_ledger_snapshots_org_id');
            $table->unique(['organization_id', 'account_id', 'as_of_date'], 'uq_ledger_snapshots_org_account_date');
            $table->index(['organization_id', 'as_of_date'], 'idx_ledger_snapshots_org_date');

            $table->foreign(['organization_id', 'account_id'], 'fk_ledger_snapshots_account')
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
        Schema::dropIfExists('ledger_balance_snapshots');
    }
};
