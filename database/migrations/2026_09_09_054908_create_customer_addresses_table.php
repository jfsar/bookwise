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
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('type', 30);
            $table->string('recipient_name', 200)->nullable();
            $table->string('address_line_1', 255);
            $table->string('address_line_2', 255)->nullable();
            $table->string('city', 100);
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->char('country_code', 2)->default('PH');
            $table->timestamps();

            $table->foreign(['organization_id', 'customer_id'])
                ->references(['organization_id', 'id'])
                ->on('customers')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE customer_addresses ADD CONSTRAINT ck_customer_addresses_country_code CHECK (country_code ~ \'^[A-Z]{2}$\')');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
