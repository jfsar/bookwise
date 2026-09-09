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
        Schema::create('bank_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->string('source', 50);
            $table->string('file_name', 255);
            $table->string('file_checksum', 128)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('rows_read')->default(0);
            $table->unsignedInteger('rows_imported')->default(0);
            $table->unsignedInteger('rows_skipped')->default(0);
            $table->unsignedInteger('rows_failed')->default(0);
            $table->text('error_summary')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->foreign(['organization_id', 'bank_account_id'])
                ->references(['organization_id', 'id'])
                ->on('bank_accounts')
                ->restrictOnDelete();

            $table->index(['organization_id', 'bank_account_id', 'status'], 'idx_import_batch_org_account_status');
        });

        DB::statement('ALTER TABLE bank_import_batches ADD CONSTRAINT ck_import_batch_status CHECK (status IN (\'pending\', \'processing\', \'completed\', \'failed\'))');
        DB::statement('ALTER TABLE bank_import_batches ADD CONSTRAINT ck_import_batch_rows CHECK (rows_read >= 0 AND rows_imported >= 0 AND rows_skipped >= 0 AND rows_failed >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_import_batches');
    }
};
