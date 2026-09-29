<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * V1.1 §0.28: full bank account numbers are sensitive and must not be
     * stored in plaintext. The full number lives only in
     * `encrypted_account_number` (encrypted at rest via the model cast);
     * `masked_account_number` and `last4` handle display surfaces.
     */
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn('account_number');
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->string('account_number', 255)->nullable();
        });
    }
};
