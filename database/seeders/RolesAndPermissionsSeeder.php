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

        $guards = ['web', 'sanctum'];

        $permissionsMap = [
            ['id' => 5, 'name' => 'Appointment scheduling'],
            ['id' => 8, 'name' => 'Billing and invoicing'],
            ['id' => 7, 'name' => 'Check-in/check-out'],
            ['id' => 23, 'name' => 'Data security and access control'],
            ['id' => 10, 'name' => 'Diagnosis and treatment planning'],
            ['id' => 9, 'name' => 'EHR access'],
            ['id' => 1, 'name' => 'Full system control'],
            ['id' => 21, 'name' => 'High-level analytics'],
            ['id' => 26, 'name' => 'HMS software configuration'],
            ['id' => 19, 'name' => 'Insurance claims management'],
            ['id' => 6, 'name' => 'Insurance verification'],
            ['id' => 16, 'name' => 'Laboratory test processing'],
            ['id' => 18, 'name' => 'Medical imaging management'],
            ['id' => 14, 'name' => 'Medication administration'],
            ['id' => 17, 'name' => 'Medication dispensing'],
            ['id' => 12, 'name' => 'Ordering and reviewing tests and imaging'],
            ['id' => 13, 'name' => 'Patient monitoring'],
            ['id' => 4, 'name' => 'Patient registration'],
            ['id' => 11, 'name' => 'Prescription and medication management'],
            ['id' => 15, 'name' => 'Recording vital signs'],
            ['id' => 3, 'name' => 'Role and permission management'],
            ['id' => 22, 'name' => 'System maintenance'],
            ['id' => 24, 'name' => 'Technical issue troubleshooting'],
            ['id' => 2, 'name' => 'User management'],
            ['id' => 25, 'name' => 'User support'],
            ['id' => 20, 'name' => 'Viewing personal health records'],
        ];

        foreach ($guards as $guard) {
            foreach ($permissionsMap as $perm) {
                Permission::updateOrCreate(
                    ['name' => $perm['name'], 'guard_name' => $guard],
                    ['name' => $perm['name'], 'guard_name' => $guard]
                );
            }
        }

        $rolesMap = [
            ['id' => 1, 'name' => 'Administrator'],
            ['id' => 12, 'name' => 'Auditor'],
            ['id' => 3, 'name' => 'Doctor/Physician'],
            ['id' => 2, 'name' => 'Front Desk/Receptionist'],
            ['id' => 8, 'name' => 'Insurance Coordinator/Billing Specialist'],
            ['id' => 11, 'name' => 'IT Support/System Administrator'],
            ['id' => 5, 'name' => 'Laboratory Technician'],
            ['id' => 10, 'name' => 'Manager/Executive'],
            ['id' => 4, 'name' => 'Nurse'],
            ['id' => 9, 'name' => 'Patient/Portal User'],
            ['id' => 6, 'name' => 'Pharmacist'],
            ['id' => 7, 'name' => 'Radiologist/Imaging Technician'],
            ['id' => 13, 'name' => 'Super-Admin'],
        ];

        foreach ($guards as $guard) {
            foreach ($rolesMap as $role) {
                $r = Role::updateOrCreate(
                    ['name' => $role['name'], 'guard_name' => $guard],
                    ['name' => $role['name'], 'guard_name' => $guard]
                );
                if ($role['name'] === 'Administrator' || $role['name'] === 'Super-Admin') {
                    $r->givePermissionTo(Permission::where('guard_name', $guard)->get());
                }
            }
        }
    }
}
