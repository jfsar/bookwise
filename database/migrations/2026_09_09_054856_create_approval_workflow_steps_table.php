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
        Schema::create('approval_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('approval_workflow_id')->constrained('approval_workflows')->restrictOnDelete();
            $table->smallInteger('sequence');
            $table->string('name', 200);
            $table->foreignId('required_role_id')->nullable()->constrained('roles')->restrictOnDelete();
            $table->foreignId('required_permission_id')->nullable()->constrained('permissions')->restrictOnDelete();
            $table->decimal('minimum_amount', 20, 4)->nullable();
            $table->decimal('maximum_amount', 20, 4)->nullable();
            $table->string('approval_mode', 30)->default('sequential');
            $table->smallInteger('minimum_approvals')->default(1);
            $table->timestamps();

            $table->unique(['organization_id', 'id']);
            $table->unique(['approval_workflow_id', 'sequence']);

            $table->foreign(['organization_id', 'approval_workflow_id'])
                ->references(['organization_id', 'id'])
                ->on('approval_workflows')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE approval_workflow_steps ADD CONSTRAINT ck_approval_workflow_steps_approval_mode CHECK (approval_mode IN (\'sequential\', \'parallel\'))');
        DB::statement('ALTER TABLE approval_workflow_steps ADD CONSTRAINT ck_approval_workflow_steps_minimum_approvals CHECK (minimum_approvals >= 1)');
        DB::statement('ALTER TABLE approval_workflow_steps ADD CONSTRAINT ck_approval_workflow_steps_minimum_amount CHECK (minimum_amount IS NULL OR minimum_amount >= 0)');
        DB::statement('ALTER TABLE approval_workflow_steps ADD CONSTRAINT ck_approval_workflow_steps_maximum_amount CHECK (maximum_amount IS NULL OR maximum_amount >= 0)');
        DB::statement('ALTER TABLE approval_workflow_steps ADD CONSTRAINT ck_approval_workflow_steps_range_order CHECK (minimum_amount IS NULL OR maximum_amount IS NULL OR minimum_amount <= maximum_amount)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_workflow_steps');
    }
};
