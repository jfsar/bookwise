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
        Schema::create('recurring_journals', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('journal_id')->constrained('journals')->restrictOnDelete();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('frequency', 30);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_run_date');
            $table->string('status', 30)->default('active');
            $table->boolean('auto_submit')->default(false);
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_run_key')->nullable();
            $table->unsignedInteger('run_count')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_journals');
    }
};
