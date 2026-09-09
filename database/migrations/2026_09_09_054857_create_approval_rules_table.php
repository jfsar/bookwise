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
        Schema::create('approval_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('workflow_id')->constrained('approval_workflows')->restrictOnDelete();
            $table->integer('priority')->default(0);
            $table->string('transaction_type', 100);
            $table->decimal('minimum_amount', 20, 4)->nullable();
            $table->decimal('maximum_amount', 20, 4)->nullable();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('roles')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'id']);

            $table->foreign(['organization_id', 'workflow_id'])
                ->references(['organization_id', 'id'])
                ->on('approval_workflows')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'account_id'])
                ->references(['organization_id', 'id'])
                ->on('accounts')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE approval_rules ADD CONSTRAINT ck_approval_rules_priority CHECK (priority >= 0)');
        DB::statement('ALTER TABLE approval_rules ADD CONSTRAINT ck_approval_rules_minimum_amount CHECK (minimum_amount IS NULL OR minimum_amount >= 0)');
        DB::statement('ALTER TABLE approval_rules ADD CONSTRAINT ck_approval_rules_maximum_amount CHECK (maximum_amount IS NULL OR maximum_amount >= 0)');
        DB::statement('ALTER TABLE approval_rules ADD CONSTRAINT ck_approval_rules_range_order CHECK (minimum_amount IS NULL OR maximum_amount IS NULL OR minimum_amount <= maximum_amount)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_rules');
    }
};
