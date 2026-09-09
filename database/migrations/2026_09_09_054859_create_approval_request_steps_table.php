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
        Schema::create('approval_request_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('approval_request_id')->constrained('approval_requests')->restrictOnDelete();
            $table->foreignId('workflow_step_id')->constrained('approval_workflow_steps')->restrictOnDelete();
            $table->string('status', 30)->default('pending');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->smallInteger('required_approvals');
            $table->smallInteger('approved_count')->default(0);
            $table->timestamp('due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['approval_request_id', 'workflow_step_id']);
            $table->unique(['organization_id', 'id']);

            $table->foreign(['organization_id', 'approval_request_id'])
                ->references(['organization_id', 'id'])
                ->on('approval_requests')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'workflow_step_id'])
                ->references(['organization_id', 'id'])
                ->on('approval_workflow_steps')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE approval_request_steps ADD CONSTRAINT ck_approval_request_steps_required_approvals CHECK (required_approvals >= 1)');
        DB::statement('ALTER TABLE approval_request_steps ADD CONSTRAINT ck_approval_request_steps_approved_count CHECK (approved_count >= 0 AND approved_count <= required_approvals)');
        DB::statement('ALTER TABLE approval_request_steps ADD CONSTRAINT ck_approval_request_steps_status CHECK (status IN (\'pending\', \'approved\', \'rejected\', \'skipped\'))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_request_steps');
    }
};
