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
        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('report_type', 50);
            $table->jsonb('filters')->default('{}');
            $table->string('request_hash', 64);
            $table->string('status', 30)->default('queued');
            $table->string('file_name', 255)->nullable();
            $table->string('file_path', 500)->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'user_id', 'report_type', 'request_hash'], 'uq_report_exports_org_user_type_hash');
            $table->index(['organization_id', 'status', 'created_at'], 'idx_report_exports_org_status');
            $table->index(['organization_id', 'user_id', 'created_at'], 'idx_report_exports_org_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
