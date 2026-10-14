<?php

namespace App\Http\Controllers;

use App\Services\PermissionCatalog;
use App\Support\RoleStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends MasterCrudController
{
    /** Users sign in through the sanctum guard, so roles and permissions are managed for that guard. */
    private const GUARD = 'sanctum';

    private const SUPER_ADMIN = 'Super-Admin';

    protected function model(): string
    {
        return Role::class;
    }

    protected function config(): array
    {
        return [
            'title' => 'Roles',
            'singular' => 'Role',
            'route' => 'roles',
            'permission' => 'roles',
            'searchPlaceholder' => 'Search role name',
            'search' => ['name'],
            'defaultSort' => ['name', 'asc'],
            'withCount' => ['permissions'],
            'formView' => 'roles.form',
            'lede' => 'A role is a ready set of permissions. Inactive roles are hidden from the user form; nothing is deleted.',
            'protected' => fn ($role) => $role->name === self::SUPER_ADMIN,
            'columns' => [
                ['key' => 'name', 'label' => 'Role', 'sortable' => true],
                ['key' => 'permissions_count', 'label' => 'Permissions', 'align' => 'center', 'value' => fn ($r) => $r->permissions_count],
                ['key' => 'users', 'label' => 'Users', 'align' => 'center', 'value' => fn ($r) => DB::table(config('permission.table_names.model_has_roles'))->where('role_id', $r->id)->count()],
                ['key' => 'is_active', 'label' => 'Status', 'align' => 'center', 'badge' => true, 'value' => fn ($r) => RoleStatus::supported() ? ($r->is_active ? 'Active' : 'Inactive') : 'Active'],
                ['key' => 'created_at', 'label' => 'Created', 'sortable' => true, 'value' => fn ($r) => $r->created_at?->format('d M Y')],
            ],
            'fields' => [
                ['name' => 'name', 'label' => 'Role name', 'type' => 'text', 'rules' => 'required|string|max:255'],
            ],
        ];
    }

    protected function query(Request $request): Builder
    {
        return parent::query($request)->where('guard_name', self::GUARD);
    }

    protected function findRecord($id): Model
    {
        return Role::where('guard_name', self::GUARD)->with('permissions:id')->findOrFail($id);
    }

    protected function formData(array $config, Model $record): array
    {
        $permissions = Permission::where('guard_name', self::GUARD)->orderBy('name')->get();

        return parent::formData($config, $record) + [
            'catalog' => PermissionCatalog::build($permissions),
            'selected' => $record->exists ? $record->permissions->pluck('id')->map(fn ($id) => (string) $id)->all() : [],
            'locked' => $record->exists && $record->name === self::SUPER_ADMIN,
            'statusSupported' => RoleStatus::supported(),
            'roleStatus' => $record->exists && RoleStatus::supported() && ! $record->is_active ? 'Inactive' : 'Active',
        ];
    }

    protected function rules(Request $request, ?Model $record): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->where('guard_name', self::GUARD)->ignore($record?->id)],
            'status' => 'nullable|in:Active,Inactive',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id,guard_name,'.self::GUARD,
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules($request, null));
        DB::transaction(function () use ($data) {
            $role = Role::create(['name' => $data['name'], 'guard_name' => self::GUARD]);
            $this->applyStatus($role, $data['status'] ?? 'Active');
            $role->syncPermissions($this->permissionModels($data['permissions'] ?? []));
        });

        return redirect()->route('roles.index')->with('success', 'Role created successfully.');
    }

    public function update(Request $request, $id)
    {
        $role = $this->findRecord($id);
        $data = $request->validate($this->rules($request, $role));

        if ($role->name === self::SUPER_ADMIN && $data['name'] !== self::SUPER_ADMIN) {
            return back()->withErrors(['name' => 'The Super-Admin role cannot be renamed.'])->withInput();
        }

        if (($data['status'] ?? 'Active') === 'Inactive' && $this->hasUsers($role)) {
            return back()->withErrors(['status' => 'This role is assigned to users, so it cannot be made inactive.'])->withInput();
        }

        DB::transaction(function () use ($role, $data) {
            $role->update(['name' => $data['name']]);
            if ($role->name !== self::SUPER_ADMIN) {
                $this->applyStatus($role, $data['status'] ?? 'Active');
            }
            if ($role->name !== self::SUPER_ADMIN) {
                $role->syncPermissions($this->permissionModels($data['permissions'] ?? []));
            }
        });

        return redirect()->route('roles.index')->with('success', 'Role updated successfully.');
    }

    protected function cannotDelete(Model $record): ?string
    {
        if ($record->name === self::SUPER_ADMIN) {
            return 'The Super-Admin role cannot be deleted.';
        }
        if ($this->hasUsers($record)) {
            return 'This role is assigned to users and cannot be deleted.';
        }

        return null;
    }

    protected function deleteRecord(Model $record): void
    {
        // Permissions are shared between roles, so only the role and its pivot rows are removed.
        // (Deleting through the model fails for the "sanctum" guard: it has no provider in config/auth.php.)
        DB::transaction(function () use ($record) {
            DB::table(config('permission.table_names.role_has_permissions'))->where('role_id', $record->id)->delete();
            Role::whereKey($record->id)->toBase()->delete();
        });
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function hasUsers(Model $role): bool
    {
        return DB::table(config('permission.table_names.model_has_roles'))->where('role_id', $role->id)->exists();
    }

    private function applyStatus(Model $role, string $status): void
    {
        if (RoleStatus::supported()) {
            Role::whereKey($role->id)->toBase()->update(['is_active' => $status === 'Active']);
        }
    }

    private function permissionModels(array $ids)
    {
        return Permission::whereIn('id', $ids)->where('guard_name', self::GUARD)->get();
    }
}
