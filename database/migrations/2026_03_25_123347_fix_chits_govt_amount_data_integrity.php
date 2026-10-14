<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix chits where govt_amount was incorrectly set to the full amount
     * instead of (amount - amount_hif). This caused HIF + GOVT to exceed
     * the total amount in department-wise reports.
     *
     * Affected fee types: 108 (Chit Fee Screening OPD Female),
     * 270 (Chit Fee Screening OPD Male), and any other chits where
     * govt_amount != amount - amount_hif for non-entitled patients.
     */
    public function up(): void
    {
        DB::table('chits')
            ->where('government_non_gov', false)
            ->where('amount', '>', 0)
            ->whereRaw('govt_amount != (amount - amount_hif)')
            ->update([
                'govt_amount' => DB::raw('amount - amount_hif'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reliably reverse data correction
    }
};
