<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class PermissionController extends MasterCrudController
{
    /** Users sign in through sanctum; every permission also exists for the web guard. */
    private const GUARD = 'sanctum';

    protected function model(): string
    {
        return Permission::class;
    }

    protected function config(): array
    {
        return [
            'title' => 'Permissions',
            'singular' => 'Permission',
            'route' => 'permissions',
            'permission' => 'permissions',
            'lede' => 'Every action a role or user can be allowed to do. Give them to roles and users on the Roles and Users screens.',
            'searchPlaceholder' => 'Search permission name',
            'search' => ['name'],
            'defaultSort' => ['name', 'asc'],
            'withCount' => ['roles'],
            'columns' => [
                ['key' => 'name', 'label' => 'Permission', 'sortable' => true],
                ['key' => 'roles_count', 'label' => 'Given to roles', 'align' => 'center', 'value' => fn ($r) => $r->roles_count],
            ],
            'fields' => [
                ['name' => 'name', 'label' => 'Permission name', 'type' => 'text', 'rules' => 'required|string|max:255', 'placeholder' => 'e.g. view patients',
                    'help' => 'Use "action thing", for example "view patients" or "delete chits". It is created for both guards.'],
            ],
        ];
    }

    protected function permissionName(string $verb, string $word): string
    {
        return match ($verb) {
            'view' => 'view permissions',
            default => 'manage permissions',
        };
    }

    protected function query(Request $request): Builder
    {
        return parent::query($request)->where('guard_name', self::GUARD);
    }

    protected function findRecord($id): Model
    {
        return Permission::where('guard_name', self::GUARD)->findOrFail($id);
    }

    protected function rules(Request $request, ?Model $record): array
    {
        return ['name' => ['required', 'string', 'max:255', Rule::unique('permissions', 'name')->where('guard_name', self::GUARD)->ignore($record?->id)]];
    }

    protected function saveRecord(?Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data) {
            if ($record) {
                // keep the web and sanctum copies in step
                Permission::where('name', $record->name)->update(['name' => $data['name']]);

                return $record->refresh();
            }
            foreach (['web', self::GUARD] as $guard) {
                $created = Permission::findOrCreate($data['name'], $guard);
            }

            return $created;
        });
    }

    protected function deleteRecord(Model $record): void
    {
        // Removed with plain queries: deleting a sanctum-guard permission through the model fails
        // because that guard has no provider in config/auth.php.
        DB::transaction(function () use ($record) {
            $ids = Permission::where('name', $record->name)->pluck('id');
            DB::table(config('permission.table_names.role_has_permissions'))->whereIn('permission_id', $ids)->delete();
            DB::table(config('permission.table_names.model_has_permissions'))->whereIn('permission_id', $ids)->delete();
            Permission::whereIn('id', $ids)->toBase()->delete();
        });
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
