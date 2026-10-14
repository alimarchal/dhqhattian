<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Two dashboards: "view admin dashboard" (management overview) and "view front desk dashboard"
 * (a receptionist's own work). Nothing is taken away from anybody:
 *
 *  - the permissions are created for both guards;
 *  - Super-Admin and Administrator roles get the admin one, Front Desk/Receptionist the front desk one;
 *  - every existing user who can open the dashboard today (through a role or a direct permission)
 *    and would not get the new permission through a role receives it directly, picked by what they
 *    could see before: dashboard statistics (or Super-Admin / Administrator) -> admin dashboard,
 *    otherwise -> front desk dashboard.
 *
 * Users without "view dashboard" (and roles such as Auditor, who keep the classic dashboard) are untouched.
 */
return new class extends Migration
{
    private const ADMIN = 'view admin dashboard';

    private const FRONT_DESK = 'view front desk dashboard';

    private const GUARDS = ['web', 'sanctum'];

    public function up(): void
    {
        $now = now();
        $permissions = config('permission.table_names.permissions', 'permissions');
        $roles = config('permission.table_names.roles', 'roles');
        $rolePermissions = config('permission.table_names.role_has_permissions', 'role_has_permissions');
        $modelRoles = config('permission.table_names.model_has_roles', 'model_has_roles');
        $modelPermissions = config('permission.table_names.model_has_permissions', 'model_has_permissions');

        $ids = [];
        foreach (self::GUARDS as $guard) {
            foreach ([self::ADMIN, self::FRONT_DESK] as $name) {
                DB::table($permissions)->updateOrInsert(
                    ['name' => $name, 'guard_name' => $guard],
                    ['created_at' => $now, 'updated_at' => $now]
                );
                $ids[$guard][$name] = DB::table($permissions)->where('name', $name)->where('guard_name', $guard)->value('id');
            }
        }

        // roles
        $roleGrants = [
            'Super-Admin' => self::ADMIN,
            'Administrator' => self::ADMIN,
            'Front Desk/Receptionist' => self::FRONT_DESK,
        ];
        foreach (self::GUARDS as $guard) {
            foreach ($roleGrants as $roleName => $permissionName) {
                $roleId = DB::table($roles)->where('name', $roleName)->where('guard_name', $guard)->value('id');
                if ($roleId) {
                    DB::table($rolePermissions)->insertOrIgnore(['permission_id' => $ids[$guard][$permissionName], 'role_id' => $roleId]);
                }
            }
        }

        // existing users (they sign in with the sanctum guard)
        $guard = 'sanctum';
        $permissionNames = DB::table($permissions)->where('guard_name', $guard)->pluck('name', 'id');
        $userModel = 'App\\Models\\User';

        foreach (DB::table('users')->pluck('id') as $userId) {
            $roleRows = DB::table($modelRoles)->where('model_id', $userId)->where('model_type', $userModel)->pluck('role_id');
            $roleNames = DB::table($roles)->whereIn('id', $roleRows)->where('guard_name', $guard)->pluck('name')->all();

            $viaRoles = DB::table($rolePermissions)->whereIn('role_id', $roleRows)->pluck('permission_id')
                ->map(fn ($id) => $permissionNames[$id] ?? null)->filter()->all();
            $direct = DB::table($modelPermissions)->where('model_id', $userId)->where('model_type', $userModel)->pluck('permission_id')
                ->map(fn ($id) => $permissionNames[$id] ?? null)->filter()->all();
            $effective = array_unique(array_merge($viaRoles, $direct));

            if (! in_array('view dashboard', $effective, true)) {
                continue;
            }

            $wanted = (in_array('view dashboard statistics', $effective, true) || array_intersect($roleNames, ['Super-Admin', 'Administrator']))
                ? self::ADMIN
                : self::FRONT_DESK;

            if (! in_array($wanted, $effective, true)) {
                DB::table($modelPermissions)->insertOrIgnore([
                    'permission_id' => $ids[$guard][$wanted],
                    'model_id' => $userId,
                    'model_type' => $userModel,
                ]);
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissions = config('permission.table_names.permissions', 'permissions');
        $ids = DB::table($permissions)->whereIn('name', [self::ADMIN, self::FRONT_DESK])->pluck('id');

        DB::table(config('permission.table_names.role_has_permissions', 'role_has_permissions'))->whereIn('permission_id', $ids)->delete();
        DB::table(config('permission.table_names.model_has_permissions', 'model_has_permissions'))->whereIn('permission_id', $ids)->delete();
        DB::table($permissions)->whereIn('id', $ids)->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
