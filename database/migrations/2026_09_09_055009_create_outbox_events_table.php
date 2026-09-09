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
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->restrictOnDelete();
            $table->string('aggregate_type', 100);
            $table->unsignedBigInteger('aggregate_id');
            $table->unsignedBigInteger('aggregate_version')->nullable();
            $table->string('event_type', 100);
            $table->jsonb('payload');
            $table->string('status', 30)->default('pending');
            $table->timestamp('available_at');
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'available_at'], 'idx_outbox_status_available');
            $table->index(['aggregate_type', 'aggregate_id'], 'idx_outbox_aggregate');
            $table->index(['organization_id'], 'idx_outbox_org');
        });

        DB::statement('ALTER TABLE outbox_events ADD CONSTRAINT ck_outbox_status CHECK (status IN (\'pending\', \'published\', \'failed\'))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
