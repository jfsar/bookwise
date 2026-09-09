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
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('key', 255);
            $table->string('operation_type', 100);
            $table->string('request_hash', 128);
            $table->string('status', 30)->default('pending');
            $table->string('resource_type', 150)->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->integer('response_code')->nullable();
            $table->jsonb('response_payload')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'key', 'operation_type']);
            $table->unique(['organization_id', 'id']);

            $table->index(['organization_id', 'status'], 'idx_idempotency_org_status');
            $table->index(['expires_at'], 'idx_idempotency_expires');
        });

        DB::statement('ALTER TABLE idempotency_keys ADD CONSTRAINT ck_idempotency_status CHECK (status IN (\'pending\', \'completed\', \'failed\', \'expired\'))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
