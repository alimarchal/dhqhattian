<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix govt_amount for chits with fee_type_id 108 and 270 where
     * govt_amount was incorrectly set to amount instead of amount - amount_hif.
     */
    public function up(): void
    {
        DB::table('chits')
            ->whereIn('fee_type_id', [108, 270])
            ->update(['govt_amount' => DB::raw('amount - amount_hif')]);
    }

    /**
     * Reverse the migration by setting govt_amount back to amount.
     */
    public function down(): void
    {
        DB::table('chits')
            ->whereIn('fee_type_id', [108, 270])
            ->update(['govt_amount' => DB::raw('amount')]);
    }
};
