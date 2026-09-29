<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * V1.1 §0.29 audit coverage: bank accounts carry creator/updater so the
     * audit chain and account-change events stay fully attributable. The
     * Phase 0 bank_accounts migration omitted these columns, but the model,
     * actions, and feature tests all rely on them.
     *
     * Nullable to allow existing rows to migrate without a backfill.
     */
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('status')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn(['created_by', 'updated_by']);
        });
    }
};
