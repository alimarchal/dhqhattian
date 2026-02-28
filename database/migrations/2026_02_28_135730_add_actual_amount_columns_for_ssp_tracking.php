<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds actual_amount columns to track real fee values for SSP (Sehat Sahulat Program)
     * patients. These columns store what the fee would have been if the patient were
     * paying, enabling future insurance claim reporting.
     */
    public function up(): void
    {
        Schema::table('chits', function (Blueprint $table) {
            $table->decimal('actual_amount', 15, 2)->default(0)->after('govt_amount');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('actual_total_amount', 15, 2)->default(0)->after('govt_amount');
        });

        Schema::table('patient_tests', function (Blueprint $table) {
            $table->decimal('actual_total_amount', 15, 2)->default(0)->after('govt_amount');
        });

        Schema::table('admissions', function (Blueprint $table) {
            $table->unsignedBigInteger('government_department_id')->nullable()->after('patient_id');
            $table->decimal('actual_total_amount', 15, 2)->default(0)->after('government_department_id');
        });

        // Insert Sehat Sahulat Program department if it doesn't already exist
        $exists = DB::table('government_departments')->where('id', 95)->exists();
        if (! $exists) {
            DB::table('government_departments')->insert([
                'id' => 95,
                'name' => 'SEHAT SAHULAT PROGRAM',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chits', function (Blueprint $table) {
            $table->dropColumn('actual_amount');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('actual_total_amount');
        });

        Schema::table('patient_tests', function (Blueprint $table) {
            $table->dropColumn('actual_total_amount');
        });

        Schema::table('admissions', function (Blueprint $table) {
            $table->dropColumn(['government_department_id', 'actual_total_amount']);
        });
    }
};
