<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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

        // =====================================================================
        // 7. PERMISSIONS SYNC — Comment out this section after first run
        //    to prevent re-running on every migrate.
        // =====================================================================
        $this->syncPermissionsAndRoles();
        // =====================================================================
    }

    /**
     * Sync all permissions, roles, and assign permissions to users.
     * This replaces old generic permissions with new granular ones.
     *
     * COMMENT OUT the call to this method in up() after first production run.
     */
    private function syncPermissionsAndRoles(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $guards = ['web', 'sanctum'];

        // New granular permissions
        $permissions = [
            // Dashboard
            'view dashboard',
            'view dashboard statistics',

            // Patients
            'view patients',
            'create patients',
            'edit patients',
            'delete patients',

            // Chits/OPD
            'view chits',
            'create chits',
            'edit chits',
            'delete chits',

            // Invoices
            'view invoices',
            'create invoices',
            'edit invoices',
            'delete invoices',

            // Admissions
            'view admissions',
            'create admissions',
            'edit admissions',
            'delete admissions',

            // Reports
            'view reports',
            'view opd reports',
            'view daily reports',
            'view department reports',
            'view admission reports',
            'view emergency reports',
            'view ssp reports',

            // Departments
            'view departments',
            'create departments',
            'edit departments',
            'delete departments',

            // Government Departments
            'view government departments',
            'create government departments',
            'edit government departments',
            'delete government departments',

            // Fee Types
            'view fee types',
            'create fee types',
            'edit fee types',
            'delete fee types',

            // Fee Categories
            'view fee categories',
            'create fee categories',
            'edit fee categories',
            'delete fee categories',

            // Users
            'view users',
            'create users',
            'edit users',
            'delete users',

            // Roles & Permissions
            'view roles',
            'create roles',
            'edit roles',
            'delete roles',
            'view permissions',
            'manage permissions',
            'assign permissions',

            // System Data
            'view diseases',
            'manage diseases',
            'view districts',
            'manage districts',
            'view tehsils',
            'manage tehsils',
            'view admission wards',
            'manage admission wards',
        ];

        // Step A: Remove ALL old role->permission assignments
        DB::table('role_has_permissions')->truncate();

        // Step B: Remove ALL old user->permission assignments
        DB::table('model_has_permissions')->truncate();

        // Step C: Delete old generic permissions that are no longer used
        Permission::whereNotIn('name', $permissions)->delete();

        // Step D: Create/update all new granular permissions
        foreach ($guards as $guard) {
            foreach ($permissions as $permission) {
                Permission::updateOrCreate(
                    ['name' => $permission, 'guard_name' => $guard],
                    ['name' => $permission, 'guard_name' => $guard]
                );
            }
        }

        // Step E: Create/update all roles
        $roleNames = [
            'Administrator',
            'Auditor',
            'Doctor/Physician',
            'Front Desk/Receptionist',
            'Insurance Coordinator/Billing Specialist',
            'IT Support/System Administrator',
            'Laboratory Technician',
            'Manager/Executive',
            'Nurse',
            'Patient/Portal User',
            'Pharmacist',
            'Radiologist/Imaging Technician',
            'Super-Admin',
        ];

        foreach ($guards as $guard) {
            foreach ($roleNames as $roleName) {
                Role::updateOrCreate(
                    ['name' => $roleName, 'guard_name' => $guard],
                    ['name' => $roleName, 'guard_name' => $guard]
                );
            }
        }

        // Step F: Assign permissions to roles
        foreach ($guards as $guard) {
            // Super-Admin gets ALL permissions
            $superAdmin = Role::where('name', 'Super-Admin')->where('guard_name', $guard)->first();
            if ($superAdmin) {
                $superAdmin->syncPermissions(Permission::where('guard_name', $guard)->get());
            }

            // Administrator gets all except role/permission management
            $admin = Role::where('name', 'Administrator')->where('guard_name', $guard)->first();
            if ($admin) {
                $adminPermissions = Permission::where('guard_name', $guard)
                    ->whereNotIn('name', [
                        'view roles', 'create roles', 'edit roles', 'delete roles',
                        'view permissions', 'manage permissions', 'assign permissions',
                    ])
                    ->get();
                $admin->syncPermissions($adminPermissions);
            }

            // Front Desk/Receptionist — daily operational permissions only
            $frontDesk = Role::where('name', 'Front Desk/Receptionist')->where('guard_name', $guard)->first();
            if ($frontDesk) {
                $frontDeskPermissions = Permission::where('guard_name', $guard)
                    ->whereIn('name', [
                        'view dashboard',
                        'view patients',
                        'create patients',
                        'edit patients',
                        'view chits',
                        'create chits',
                        'edit chits',
                        'view invoices',
                        'create invoices',
                        'view admissions',
                        'create admissions',
                        'edit admissions',
                    ])
                    ->get();
                $frontDesk->syncPermissions($frontDeskPermissions);
            }

            // Auditor — read-only access to everything + reports
            $auditor = Role::where('name', 'Auditor')->where('guard_name', $guard)->first();
            if ($auditor) {
                $auditorPermissions = Permission::where('guard_name', $guard)
                    ->whereIn('name', [
                        'view dashboard',
                        'view dashboard statistics',
                        'view patients',
                        'view chits',
                        'view invoices',
                        'view admissions',
                        'view reports',
                        'view opd reports',
                        'view daily reports',
                        'view department reports',
                        'view admission reports',
                        'view emergency reports',
                        'view ssp reports',
                        'view departments',
                        'view government departments',
                        'view fee types',
                        'view fee categories',
                    ])
                    ->get();
                $auditor->syncPermissions($auditorPermissions);
            }
        }

        // Step G: Assign essential permissions DIRECTLY to ALL users
        //         These are direct user permissions (removable via admin UI).
        // =====================================================================
        $essentialPermissions = [
            'view dashboard',
            'view patients',
            'create patients',
            'view chits',
            'create chits',
            'view invoices',
            'create invoices',
            'view admissions',
            'create admissions',
        ];

        $essentialPerms = Permission::where('guard_name', 'sanctum')
            ->whereIn('name', $essentialPermissions)
            ->get();

        User::all()->each(function ($user) use ($essentialPerms) {
            $user->givePermissionTo($essentialPerms);
        });
        // =====================================================================

        // Step G2: Dr. Khawaja Moosa — dashboard + reports only (no admin access)
        // =====================================================================
        $moosa = User::where('email', 'khawaja.moosa@yahoo.com')->first();
        if ($moosa) {
            // Remove Administrator role
            $moosa->syncRoles([]);

            // Give only dashboard + all report permissions
            $moosaPermissions = Permission::where('guard_name', 'sanctum')
                ->whereIn('name', [
                    'view dashboard',
                    'view dashboard statistics',
                    'view reports',
                    'view opd reports',
                    'view daily reports',
                    'view department reports',
                    'view admission reports',
                    'view emergency reports',
                    'view ssp reports',
                ])
                ->get();
            $moosa->syncPermissions($moosaPermissions);
        }
        // =====================================================================

        // Step H: Reset permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
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
