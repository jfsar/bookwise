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
        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('fiscal_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->smallInteger('period_number');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 30)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('closed_reason')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('reopen_reason')->nullable();
            $table->timestamps();

            $table->unique(['fiscal_year_id', 'period_number']);
            $table->unique(['organization_id', 'id']);

            $table->foreign(['organization_id', 'fiscal_year_id'])
                ->references(['organization_id', 'id'])
                ->on('fiscal_years')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE accounting_periods ADD CONSTRAINT ck_accounting_periods_dates CHECK (start_date <= end_date)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
    }
};
