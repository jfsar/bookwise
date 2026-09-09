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
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('requestable_type', 100);
            $table->unsignedBigInteger('requestable_id');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('required_role_id')->nullable()->constrained('roles')->restrictOnDelete();
            $table->decimal('amount', 20, 4)->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamp('submitted_at');
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'status', 'submitted_at'], 'idx_approval_org_status');
        });

        DB::statement('ALTER TABLE approval_requests ADD CONSTRAINT ck_approval_requests_amount CHECK (amount IS NULL OR amount > 0)');
        DB::statement('ALTER TABLE approval_requests ADD CONSTRAINT ck_approval_requests_status CHECK (status IN (\'pending\', \'in_review\', \'approved\', \'rejected\', \'withdrawn\', \'cancelled\'))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
