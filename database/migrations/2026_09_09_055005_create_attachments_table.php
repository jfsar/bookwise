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
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('attachable_type', 150);
            $table->unsignedBigInteger('attachable_id');
            $table->string('original_name', 255);
            $table->string('stored_name', 255);
            $table->string('disk', 50)->default('local');
            $table->text('path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->string('checksum', 128)->nullable();
            $table->string('visibility', 20)->default('private');
            $table->string('scan_status', 30)->default('pending');
            $table->string('scan_result', 100)->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'attachable_type', 'attachable_id'], 'idx_attachment_org_attachable');
            $table->index(['uploaded_by'], 'idx_attachment_uploaded_by');
        });

        DB::statement('ALTER TABLE attachments ADD CONSTRAINT ck_attachment_visibility CHECK (visibility IN (\'private\', \'organization\', \'public\'))');
        DB::statement('ALTER TABLE attachments ADD CONSTRAINT ck_attachment_scan_status CHECK (scan_status IN (\'pending\', \'clean\', \'infected\', \'failed\'))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
