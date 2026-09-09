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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_type_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 50);
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('normal_balance', 10);
            $table->string('account_category', 50)->nullable();
            $table->boolean('is_posting_account')->default(false);
            $table->boolean('is_system_account')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('allow_manual_posting')->default(true);
            $table->foreignId('currency_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->unique(['organization_id', 'id']);

            $table->foreign('parent_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->foreign(['organization_id', 'parent_id'])
                ->references(['organization_id', 'id'])
                ->on('accounts')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE accounts ADD CONSTRAINT ck_accounts_normal_balance CHECK (normal_balance IN (\'debit\', \'credit\'))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
