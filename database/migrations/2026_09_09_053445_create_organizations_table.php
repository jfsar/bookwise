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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name', 200);
            $table->string('display_name', 200)->nullable();
            $table->string('code', 50)->unique();
            $table->string('registration_number', 100)->nullable();
            $table->string('tax_number', 100)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('address_line_1', 255)->nullable();
            $table->string('address_line_2', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->char('country_code', 2);
            $table->foreignId('base_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->string('timezone', 100);
            $table->smallInteger('fiscal_year_start_month');
            $table->string('date_format', 30);
            $table->smallInteger('decimal_places')->default(2);
            $table->string('status', 30)->default('active');
            $table->jsonb('settings')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE organizations ADD CONSTRAINT ck_organizations_country_code_format CHECK (country_code ~ \'^[A-Z]{2}$\')');
        DB::statement('ALTER TABLE organizations ADD CONSTRAINT ck_organizations_fiscal_year_start_month_between CHECK (fiscal_year_start_month BETWEEN 1 AND 12)');
        DB::statement('ALTER TABLE organizations ADD CONSTRAINT ck_organizations_decimal_places_between CHECK (decimal_places BETWEEN 0 AND 9)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
