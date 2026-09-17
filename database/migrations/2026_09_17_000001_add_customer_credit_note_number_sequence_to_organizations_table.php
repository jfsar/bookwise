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
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->bigInteger('customer_credit_note_number_sequence')->default(0)->after('customer_payment_number_sequence');
        });

        $rows = DB::table('organizations as o')
            ->leftJoin('customer_credit_notes as ccn', 'ccn.organization_id', '=', 'o.id')
            ->select('o.id', DB::raw('COALESCE(MAX(CAST(REPLACE(ccn.credit_note_number, \'CN-\', \'\') AS INTEGER)), 0) as max_sequence'))
            ->groupBy('o.id')
            ->get();

        foreach ($rows as $row) {
            DB::table('organizations')
                ->where('id', $row->id)
                ->update(['customer_credit_note_number_sequence' => $row->max_sequence]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('customer_credit_note_number_sequence');
        });
    }
};
