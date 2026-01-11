<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'sanctum';

        $permissions = [
            ['id' => 5, 'name' => 'Appointment scheduling', 'guard_name' => $guard],
            ['id' => 8, 'name' => 'Billing and invoicing', 'guard_name' => $guard],
            ['id' => 7, 'name' => 'Check-in/check-out', 'guard_name' => $guard],
            ['id' => 23, 'name' => 'Data security and access control', 'guard_name' => $guard],
            ['id' => 10, 'name' => 'Diagnosis and treatment planning', 'guard_name' => $guard],
            ['id' => 9, 'name' => 'EHR access', 'guard_name' => $guard],
            ['id' => 1, 'name' => 'Full system control', 'guard_name' => $guard],
            ['id' => 21, 'name' => 'High-level analytics', 'guard_name' => $guard],
            ['id' => 26, 'name' => 'HMS software configuration', 'guard_name' => $guard],
            ['id' => 19, 'name' => 'Insurance claims management', 'guard_name' => $guard],
            ['id' => 6, 'name' => 'Insurance verification', 'guard_name' => $guard],
            ['id' => 16, 'name' => 'Laboratory test processing', 'guard_name' => $guard],
            ['id' => 18, 'name' => 'Medical imaging management', 'guard_name' => $guard],
            ['id' => 14, 'name' => 'Medication administration', 'guard_name' => $guard],
            ['id' => 17, 'name' => 'Medication dispensing', 'guard_name' => $guard],
            ['id' => 12, 'name' => 'Ordering and reviewing tests and imaging', 'guard_name' => $guard],
            ['id' => 13, 'name' => 'Patient monitoring', 'guard_name' => $guard],
            ['id' => 4, 'name' => 'Patient registration', 'guard_name' => $guard],
            ['id' => 11, 'name' => 'Prescription and medication management', 'guard_name' => $guard],
            ['id' => 15, 'name' => 'Recording vital signs', 'guard_name' => $guard],
            ['id' => 3, 'name' => 'Role and permission management', 'guard_name' => $guard],
            ['id' => 22, 'name' => 'System maintenance', 'guard_name' => $guard],
            ['id' => 24, 'name' => 'Technical issue troubleshooting', 'guard_name' => $guard],
            ['id' => 2, 'name' => 'User management', 'guard_name' => $guard],
            ['id' => 25, 'name' => 'User support', 'guard_name' => $guard],
            ['id' => 20, 'name' => 'Viewing personal health records', 'guard_name' => $guard],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['id' => $permission['id']], $permission);
        }

        $roles = [
            ['id' => 1, 'name' => 'Administrator', 'guard_name' => $guard],
            ['id' => 12, 'name' => 'Auditor', 'guard_name' => $guard],
            ['id' => 3, 'name' => 'Doctor/Physician', 'guard_name' => $guard],
            ['id' => 2, 'name' => 'Front Desk/Receptionist', 'guard_name' => $guard],
            ['id' => 8, 'name' => 'Insurance Coordinator/Billing Specialist', 'guard_name' => $guard],
            ['id' => 11, 'name' => 'IT Support/System Administrator', 'guard_name' => $guard],
            ['id' => 5, 'name' => 'Laboratory Technician', 'guard_name' => $guard],
            ['id' => 10, 'name' => 'Manager/Executive', 'guard_name' => $guard],
            ['id' => 4, 'name' => 'Nurse', 'guard_name' => $guard],
            ['id' => 9, 'name' => 'Patient/Portal User', 'guard_name' => $guard],
            ['id' => 6, 'name' => 'Pharmacist', 'guard_name' => $guard],
            ['id' => 7, 'name' => 'Radiologist/Imaging Technician', 'guard_name' => $guard],
            ['id' => 13, 'name' => 'Super-Admin', 'guard_name' => $guard], // Added Super-Admin as per logic
        ];

        foreach ($roles as $role) {
            $r = Role::updateOrCreate(['id' => $role['id']], $role);
            if ($role['name'] === 'Administrator' || $role['name'] === 'Super-Admin') {
                $r->givePermissionTo(Permission::all());
            }
        }
    }
}
