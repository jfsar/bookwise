<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->bigInteger('vendor_payment_number_sequence')->default(0)->after('vendor_bill_number_sequence');
        });

        $rows = DB::table('organizations as o')
            ->leftJoin('vendor_payments as vp', 'vp.organization_id', '=', 'o.id')
            ->select('o.id', DB::raw('COALESCE(MAX(CAST(REPLACE(vp.payment_number, \'VPAY-\', \'\') AS INTEGER)), 0) as max_sequence'))
            ->groupBy('o.id')
            ->get();

        foreach ($rows as $row) {
            DB::table('organizations')
                ->where('id', $row->id)
                ->update(['vendor_payment_number_sequence' => $row->max_sequence]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('vendor_payment_number_sequence');
        });
    }
};
