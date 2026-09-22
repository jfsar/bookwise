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
        Schema::table('organizations', function (Blueprint $table) {
            $table->bigInteger('vendor_bill_number_sequence')->default(0)->after('settings');
        });

        $rows = DB::table('organizations as o')
            ->leftJoin('vendor_bills as vb', 'vb.organization_id', '=', 'o.id')
            ->select('o.id', DB::raw('COALESCE(MAX(CAST(REPLACE(vb.bill_number, \'BILL-\', \'\') AS INTEGER)), 0) as max_sequence'))
            ->groupBy('o.id')
            ->get();

        foreach ($rows as $row) {
            DB::table('organizations')
                ->where('id', $row->id)
                ->update(['vendor_bill_number_sequence' => $row->max_sequence]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('vendor_bill_number_sequence');
        });
    }
};
