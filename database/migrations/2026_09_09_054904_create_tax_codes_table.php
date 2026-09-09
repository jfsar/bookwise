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
        Schema::create('tax_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name', 100);
            $table->string('type', 50);
            $table->string('jurisdiction', 10)->default('PH');
            $table->foreignId('tax_account_id')->constrained('accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->unique(['organization_id', 'id']);

            $table->foreign(['organization_id', 'tax_account_id'])
                ->references(['organization_id', 'id'])
                ->on('accounts')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE tax_codes ADD CONSTRAINT ck_tax_codes_type CHECK (type IN (\'vat\', \'withholding_tax\', \'percentage_tax\', \'excise\', \'zero_rated\', \'exempt\'))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_codes');
    }
};
