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
        Schema::table('approval_actions', function (Blueprint $table) {
            $table->foreignId('approval_request_step_id')->after('approval_request_id')->constrained('approval_request_steps')->restrictOnDelete();
            $table->foreign(['organization_id', 'approval_request_step_id'], 'fk_approval_actions_org_request_step')
                ->references(['organization_id', 'id'])
                ->on('approval_request_steps')
                ->restrictOnDelete();
            $table->index(['approval_request_id', 'approval_request_step_id', 'user_id'], 'idx_approval_actions_step_dedupe');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('approval_actions', function (Blueprint $table) {
            $table->dropIndex('idx_approval_actions_step_dedupe');
            $table->dropForeign('fk_approval_actions_org_request_step');
            $table->dropConstrainedForeignId('approval_request_step_id');
        });
    }
};
