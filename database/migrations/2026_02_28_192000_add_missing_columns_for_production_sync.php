<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * This migration safely adds columns that exist in the consolidated
     * migrations but were missing from the production database.
     *
     * It uses Schema::hasColumn() checks so it's safe to run on both:
     * - Production DB (imported backup, missing these columns)
     * - Fresh installs (columns already exist from consolidated migrations)
     *
     * It also cleans up orphaned migration records from the old codebase
     * that no longer have corresponding migration files.
     */
    public function up(): void
    {
        // 1. Add missing columns to chits table
        Schema::table('chits', function (Blueprint $table) {
            if (! Schema::hasColumn('chits', 'actual_amount')) {
                $table->decimal('actual_amount', 15, 2)->default(0)->after('govt_amount');
            }
            if (! Schema::hasColumn('chits', 'sehat_sahulat_visit_no')) {
                $table->string('sehat_sahulat_visit_no')->nullable()->after('designation');
            }
        });

        // 2. Add missing columns to patients table
        Schema::table('patients', function (Blueprint $table) {
            if (! Schema::hasColumn('patients', 'sehat_sahulat_patient_id')) {
                $table->string('sehat_sahulat_patient_id')->nullable()->after('government_card_no');
            }
            if (! Schema::hasColumn('patients', 'sehat_sahulat_visit_no')) {
                $table->string('sehat_sahulat_visit_no')->nullable()->after('sehat_sahulat_patient_id');
            }
        });

        // 3. Add missing columns to admissions table
        Schema::table('admissions', function (Blueprint $table) {
            if (! Schema::hasColumn('admissions', 'government_department_id')) {
                $table->unsignedBigInteger('government_department_id')->nullable()->after('patient_id');
            }
            if (! Schema::hasColumn('admissions', 'actual_total_amount')) {
                $table->decimal('actual_total_amount', 15, 2)->default(0)->after('status');
            }
        });

        // 4. Add missing column to invoices table
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'actual_total_amount')) {
                $table->decimal('actual_total_amount', 15, 2)->default(0)->after('total_amount');
            }
        });

        // 5. Add missing column to patient_tests table
        Schema::table('patient_tests', function (Blueprint $table) {
            if (! Schema::hasColumn('patient_tests', 'actual_total_amount')) {
                $table->decimal('actual_total_amount', 15, 2)->default(0)->after('total_amount');
            }
        });

        // 6. Clean up orphaned migration records that no longer have files
        $orphanedMigrations = [
            '2025_11_23_195215_add_soft_deletes_to_invoices_and_patient_tests_tables',
            '2026_01_30_154652_add_sehat_sahulat_columns_to_patients_and_chits_tables',
            '2026_01_30_155205_add_sehat_sahulat_visit_no_to_patients_table',
            '2026_01_30_155605_fix_government_departments_sequence',
            '2026_02_25_200840_fix_users_id_sequence',
            '2026_02_28_130552_fix_chit_govt_amount_for_screening_opd_fee_types',
            '2026_02_28_135730_add_actual_amount_columns_for_ssp_tracking',
        ];

        foreach ($orphanedMigrations as $migration) {
            DB::table('migrations')->where('migration', $migration)->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_tests', function (Blueprint $table) {
            if (Schema::hasColumn('patient_tests', 'actual_total_amount')) {
                $table->dropColumn('actual_total_amount');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'actual_total_amount')) {
                $table->dropColumn('actual_total_amount');
            }
        });

        Schema::table('admissions', function (Blueprint $table) {
            if (Schema::hasColumn('admissions', 'actual_total_amount')) {
                $table->dropColumn('actual_total_amount');
            }
            if (Schema::hasColumn('admissions', 'government_department_id')) {
                $table->dropColumn('government_department_id');
            }
        });

        Schema::table('patients', function (Blueprint $table) {
            if (Schema::hasColumn('patients', 'sehat_sahulat_visit_no')) {
                $table->dropColumn('sehat_sahulat_visit_no');
            }
            if (Schema::hasColumn('patients', 'sehat_sahulat_patient_id')) {
                $table->dropColumn('sehat_sahulat_patient_id');
            }
        });

        Schema::table('chits', function (Blueprint $table) {
            if (Schema::hasColumn('chits', 'sehat_sahulat_visit_no')) {
                $table->dropColumn('sehat_sahulat_visit_no');
            }
            if (Schema::hasColumn('chits', 'actual_amount')) {
                $table->dropColumn('actual_amount');
            }
        });
    }
};
