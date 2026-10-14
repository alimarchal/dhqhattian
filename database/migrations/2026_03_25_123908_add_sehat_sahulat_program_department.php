<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add "Sehat Sahulat Program" as government_department_id = 95.
     * The application code (ChitController, PatientController, ReportsController,
     * StorePatientRequest) hardcodes ID 95 for SSP logic.
     *
     * Also fixes the PostgreSQL sequence so future inserts get the correct next ID.
     */
    public function up(): void
    {
        $exists = DB::table('government_departments')->where('id', 95)->exists();

        if (! $exists) {
            DB::table('government_departments')->insert([
                'id' => 95,
                'name' => 'SEHAT SAHULAT PROGRAM',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Fix PostgreSQL sequence to avoid duplicate key errors on future inserts
        $maxId = DB::table('government_departments')->max('id');
        DB::statement("SELECT setval('government_departments_id_seq', {$maxId})");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('government_departments')->where('id', 95)->delete();
    }
};
