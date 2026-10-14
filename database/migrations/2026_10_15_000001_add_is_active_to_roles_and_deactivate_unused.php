<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds roles.is_active and switches off the roles nobody uses.
 *
 * Safe for existing users: a role is only deactivated when no user has it and it gives no
 * permission, and the four roles the application relies on are never touched. Inactive roles
 * are simply left out of the "give a role" lists; nothing is deleted, and they can be switched
 * back on from Settings > Roles.
 */
return new class extends Migration
{
    /** Roles the application uses by name (or that real users hold) - never deactivated. */
    private const KEEP = ['Super-Admin', 'Administrator', 'Auditor', 'Front Desk/Receptionist'];

    public function up(): void
    {
        $table = config('permission.table_names.roles', 'roles');

        if (! Schema::hasColumn($table, 'is_active')) {
            Schema::table($table, function ($t) {
                $t->boolean('is_active')->default(true);
            });
        }

        $modelRoles = config('permission.table_names.model_has_roles', 'model_has_roles');
        $rolePermissions = config('permission.table_names.role_has_permissions', 'role_has_permissions');

        DB::table($table)
            ->whereNotIn('name', self::KEEP)
            ->whereNotIn('id', DB::table($modelRoles)->select('role_id'))
            ->whereNotIn('id', DB::table($rolePermissions)->select('role_id'))
            ->update(['is_active' => false]);
    }

    public function down(): void
    {
        $table = config('permission.table_names.roles', 'roles');

        if (Schema::hasColumn($table, 'is_active')) {
            Schema::table($table, function ($t) {
                $t->dropColumn('is_active');
            });
        }
    }
};
