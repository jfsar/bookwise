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
        Schema::create('recurring_journal_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('recurring_journal_id')->constrained('recurring_journals')->restrictOnDelete();
            $table->date('scheduled_run_date');
            $table->string('idempotency_key')->nullable();
            $table->string('status', 30)->default('pending');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'recurring_journal_id', 'scheduled_run_date'], 'uq_rje_org_recurring_run_date');

            $table->foreign(['organization_id', 'recurring_journal_id'], 'fk_rje_org_recurring_journal')
                ->references(['organization_id', 'id'])
                ->on('recurring_journals')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_journal_executions');
    }
};
