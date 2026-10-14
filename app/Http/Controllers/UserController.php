<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    private const ROLE_GUARD = 'sanctum';

    public function index()
    {
        $users = User::with('roles')->get();

        return view('users.index', compact('users'));
    }

    //
    public function create()
    {
        $roles = Role::query()
            ->where('guard_name', self::ROLE_GUARD)
            ->orderBy('name')
            ->pluck('name', 'id');

        return view('users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|exists:roles,id,guard_name,'.self::ROLE_GUARD,
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $role = Role::findById($request->integer('role'), self::ROLE_GUARD);
        $user->assignRole($role);

        session()->flash('status', 'User has been successfully added into database.');

        return to_route('users.index');
    }
}
