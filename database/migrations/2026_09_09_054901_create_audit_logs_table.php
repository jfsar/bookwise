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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('actor_type', 50)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 100);
            $table->string('auditable_type', 150);
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_id', 100)->nullable();
            $table->string('correlation_id', 100)->nullable();
            $table->unsignedBigInteger('sequence_no')->nullable();
            $table->char('previous_hash', 64)->nullable();
            $table->char('record_hash', 64)->nullable();
            $table->string('retention_class', 30)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement('CREATE INDEX idx_audit_org_created ON audit_logs (organization_id, created_at DESC)');
        DB::statement('CREATE INDEX idx_audit_org_entity ON audit_logs (organization_id, auditable_type, auditable_id, created_at DESC)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_audit_org_entity');
        DB::statement('DROP INDEX IF EXISTS idx_audit_org_created');

        Schema::dropIfExists('audit_logs');
    }
};
