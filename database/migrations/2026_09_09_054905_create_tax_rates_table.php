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
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('tax_code_id')->constrained('tax_codes')->restrictOnDelete();
            $table->decimal('rate', 10, 6);
            $table->string('rate_type', 30)->default('standard');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'id']);

            $table->foreign(['organization_id', 'tax_code_id'])
                ->references(['organization_id', 'id'])
                ->on('tax_codes')
                ->restrictOnDelete();
        });

        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        DB::statement('ALTER TABLE tax_rates ADD CONSTRAINT ex_tax_rates_no_overlap EXCLUDE USING gist (tax_code_id WITH =, daterange(effective_from, effective_to, \'[)\') WITH &&)');
        DB::statement('ALTER TABLE tax_rates ADD CONSTRAINT ck_tax_rates_rate CHECK (rate >= 0 AND rate <= 100)');
        DB::statement('ALTER TABLE tax_rates ADD CONSTRAINT ck_tax_rates_rate_type CHECK (rate_type IN (\'standard\', \'zero_rated\', \'exempt\', \'preferential\'))');
        DB::statement('ALTER TABLE tax_rates ADD CONSTRAINT ck_tax_rates_effective_order CHECK (effective_to IS NULL OR effective_from <= effective_to)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
