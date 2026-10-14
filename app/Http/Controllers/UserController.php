<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Services\PermissionCatalog;
use App\Support\RoleStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /** Users sign in through the sanctum guard, so roles and permissions are managed for that guard. */
    private const ROLE_GUARD = 'sanctum';

    private const SUPER_ADMIN_ROLE = 'Super-Admin';

    /** Rows-per-page choices on the list ("all" shows every matching user on one page). */
    public const PER_PAGE = [10, 50, 100, 500, 'all'];

    private const SORTS = ['name', 'email', 'created_at', 'department'];

    public function __construct()
    {
        $this->middleware('permission:view users')->only(['index', 'show']);
        $this->middleware('permission:create users')->only(['create', 'store']);
        $this->middleware('permission:edit users')->only(['edit', 'update', 'bulkUpdate']);
        $this->middleware('permission:delete users')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $requested = (string) $request->get('per_page', '10');
        $perPage = in_array($requested, array_map('strval', self::PER_PAGE), true) ? $requested : '10';

        $sort = (string) $request->get('sort', '-created_at');
        $field = ltrim($sort, '-');
        if (! in_array($field, self::SORTS, true)) {
            $field = 'created_at';
            $sort = '-created_at';
        }
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        $query = $this->filtered($request)
            ->with(['roles.permissions', 'permissions', 'department']);

        $total = (clone $query)->count();

        if ($field === 'department') {
            $query->orderBy(Department::select('name')->whereRaw('departments.id::text = users.department_id::text'), $direction);
        } else {
            $query->orderBy($field, $direction);
        }
        $query->orderBy('users.id', 'desc');

        $users = $query->paginate($perPage === 'all' ? max(1, $total) : (int) $perPage)->appends($request->query());

        return view('users.index', [
            'users' => $users,
            'roles' => RoleStatus::assignable(Role::where('guard_name', self::ROLE_GUARD))->orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'perPage' => $perPage,
            'stats' => $this->listStats($request),
        ]);
    }

    /** Apply the filter[...] query parameters (search, role, department_id, status, access, name, email). */
    private function filtered(Request $request, bool $withStatus = true)
    {
        $filters = (array) $request->input('filter', []);
        $query = User::query();

        if (filled($filters['search'] ?? null)) {
            $term = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('name', 'ilike', $term)->orWhere('email', 'ilike', $term));
        }
        if (filled($filters['name'] ?? null)) {
            $query->where('name', 'ilike', '%'.$filters['name'].'%');
        }
        if (filled($filters['email'] ?? null)) {
            $query->where('email', 'ilike', '%'.$filters['email'].'%');
        }
        if (filled($filters['role'] ?? null)) {
            $query->whereHas('roles', fn ($r) => $r->where('name', $filters['role']));
        }
        if (filled($filters['department_id'] ?? null)) {
            $query->where('department_id', $filters['department_id']);
        }
        if ($withStatus && in_array($filters['status'] ?? null, ['Active', 'Inactive'], true)) {
            $query->where('status', $filters['status'] === 'Active');
        }
        if (($filters['access'] ?? null) === 'none') {
            $query->doesntHave('roles')->doesntHave('permissions');
        }

        return $query;
    }

    /**
     * Headline numbers for the list. Tab counts follow the other filters
     * (search, role, department ...) but not the Active / Inactive tab itself.
     *
     * @return array{total: int, active: int, inactive: int, super_admins: int, no_access: int}
     */
    private function listStats(Request $request): array
    {
        $counts = $this->filtered($request, false)->reorder()
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(CASE WHEN status THEN 1 ELSE 0 END), 0) as active')
            ->first();

        return [
            'total' => (int) $counts->total,
            'active' => (int) $counts->active,
            'inactive' => (int) $counts->total - (int) $counts->active,
            'super_admins' => User::role(self::SUPER_ADMIN_ROLE, self::ROLE_GUARD)->count(),
            'no_access' => User::where('status', true)->doesntHave('roles')->doesntHave('permissions')->count(),
        ];
    }

    public function create()
    {
        return view('users.create', $this->formData(new User(['status' => true])));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'department_id' => 'nullable|exists:departments,id',
            'status' => 'required|in:Active,Inactive',
            'roles' => 'array',
            'roles.*' => 'exists:roles,id,guard_name,'.self::ROLE_GUARD,
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id,guard_name,'.self::ROLE_GUARD,
        ]);

        $this->guardSuperAdminAssignment($request->input('roles', []));

        DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'department_id' => $request->department_id,
                'status' => $request->status === 'Active',
            ]);

            $user->syncRoles($this->roleModels($request->input('roles', [])));
            $user->syncPermissions($this->permissionModels($request->input('permissions', [])));
        });

        return redirect()->route('users.index')->with('success', 'User created successfully with assigned roles and permissions.');
    }

    public function show(User $user)
    {
        $user->load(['roles.permissions', 'permissions', 'department']);

        // permission id => where it comes from (extra and / or role names)
        $grants = [];
        foreach ($user->permissions as $permission) {
            $grants[(string) $permission->id]['direct'] = true;
        }
        foreach ($user->roles as $role) {
            foreach ($role->permissions as $permission) {
                $grants[(string) $permission->id]['roles'][] = $role->name;
            }
        }

        return view('users.show', [
            'user' => $user,
            'catalog' => PermissionCatalog::build($this->permissions()),
            'grants' => $grants,
        ]);
    }

    public function edit(User $user)
    {
        return view('users.edit', $this->formData($user));
    }

    /**
     * Everything the add / edit form needs. Permissions come arranged like the
     * app's menus (see PermissionCatalog) so they are easy to give.
     *
     * @return array<string, mixed>
     */
    private function formData(User $user): array
    {
        $roles = RoleStatus::assignable(
            Role::with('permissions:id')->where('guard_name', self::ROLE_GUARD),
            $user->exists ? $user->roles->pluck('id')->all() : []
        )->orderBy('name')->get();
        $permissions = $this->permissions();
        $isNew = ! $user->exists;

        return [
            'user' => $user,
            'roles' => $roles,
            'permissions' => $permissions,
            'catalog' => PermissionCatalog::build($permissions),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'userRoles' => $isNew ? [] : $user->roles->pluck('id')->toArray(),
            'userPermissions' => $isNew ? [] : $user->getDirectPermissions()->pluck('id')->toArray(),
            'inheritedPermissions' => $isNew ? [] : $user->getPermissionsViaRoles()->pluck('id')->toArray(),
        ];
    }

    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:users,id',
            'action' => 'required|in:activate,deactivate,delete',
        ]);

        $action = $request->action;

        // Never act on the signed-in user
        $ids = array_values(array_filter($request->ids, fn ($id) => (int) $id !== auth()->id()));

        if (empty($ids)) {
            return redirect()->back()->with('error', 'No valid users selected for this action.');
        }

        if ($action === 'delete' && ! auth()->user()->hasRole(self::SUPER_ADMIN_ROLE)) {
            return redirect()->back()->with('error', 'Only a super admin can delete users.');
        }

        DB::transaction(function () use ($ids, $action) {
            if ($action === 'activate') {
                User::whereIn('id', $ids)->update(['status' => true]);
            } elseif ($action === 'deactivate') {
                User::whereIn('id', $ids)->update(['status' => false]);
            } else {
                $superAdmins = User::role(self::SUPER_ADMIN_ROLE, self::ROLE_GUARD)->count();
                foreach (User::whereIn('id', $ids)->get() as $user) {
                    if ($user->hasRole(self::SUPER_ADMIN_ROLE) && $superAdmins <= 1) {
                        continue; // keep the last super admin
                    }
                    if ($user->hasRole(self::SUPER_ADMIN_ROLE)) {
                        $superAdmins--;
                    }
                    $user->delete();
                }
            }
        });

        return redirect()->route('users.index')->with('success', $action === 'delete'
            ? 'Users deleted successfully (except any protected accounts).'
            : 'Users updated successfully.');
    }

    public function update(Request $request, User $user)
    {
        if ($user->id === auth()->id() && $request->status === 'Inactive') {
            return redirect()->back()->withErrors(['status' => 'You cannot deactivate your own account.'])->withInput();
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'department_id' => 'nullable|exists:departments,id',
            'status' => 'required|in:Active,Inactive',
            'roles' => 'array',
            'roles.*' => 'exists:roles,id,guard_name,'.self::ROLE_GUARD,
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id,guard_name,'.self::ROLE_GUARD,
        ]);

        $roleIds = $request->input('roles', []);

        // The last super admin must keep the super-admin role
        if ($user->hasRole(self::SUPER_ADMIN_ROLE)) {
            $superRoleId = Role::where('name', self::SUPER_ADMIN_ROLE)->where('guard_name', self::ROLE_GUARD)->value('id');
            if ($superRoleId && ! in_array((string) $superRoleId, array_map('strval', $roleIds), true)) {
                return redirect()->back()->withErrors(['roles' => 'You cannot remove the super-admin role from a super-admin user.'])->withInput();
            }
        }
        $this->guardSuperAdminAssignment($roleIds, $user);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'department_id' => $request->department_id,
            'status' => $request->status === 'Active',
        ];
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        DB::transaction(function () use ($user, $data, $request, $roleIds) {
            $user->update($data);
            $user->syncRoles($this->roleModels($roleIds));
            $user->syncPermissions($this->permissionModels($request->input('permissions', [])));
        });

        return redirect()->route('users.index')->with('success', 'User updated successfully with assigned roles and permissions.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        if ($user->hasRole(self::SUPER_ADMIN_ROLE) && User::role(self::SUPER_ADMIN_ROLE, self::ROLE_GUARD)->count() <= 1) {
            return redirect()->back()->withErrors(['user' => 'Cannot delete the last super admin user.']);
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }

    private function permissions()
    {
        return Permission::where('guard_name', self::ROLE_GUARD)->orderBy('name')->get();
    }

    private function roleModels(array $ids)
    {
        return Role::whereIn('id', $ids)->where('guard_name', self::ROLE_GUARD)->get();
    }

    private function permissionModels(array $ids)
    {
        return Permission::whereIn('id', $ids)->where('guard_name', self::ROLE_GUARD)->get();
    }

    /** Only a super admin may give (or keep) the super-admin role. */
    private function guardSuperAdminAssignment(array $roleIds, ?User $user = null): void
    {
        $superRoleId = Role::where('name', self::SUPER_ADMIN_ROLE)->where('guard_name', self::ROLE_GUARD)->value('id');
        if (! $superRoleId || ! in_array((string) $superRoleId, array_map('strval', $roleIds), true)) {
            return;
        }
        if ($user && $user->hasRole(self::SUPER_ADMIN_ROLE)) {
            return;
        }
        if (! auth()->user()->hasRole(self::SUPER_ADMIN_ROLE)) {
            abort(403, 'Only a super admin can give the Super-Admin role.');
        }
    }
}
