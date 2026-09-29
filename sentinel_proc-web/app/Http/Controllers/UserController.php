<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('name')->get();

        return view('users.index', compact('users'));
    }

    public function create()
    {
        $roles = UserRole::cases();

        return view('users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'role'     => ['required', Rule::enum(UserRole::class)],
            'password' => ['required', Password::min(8)->letters()->numbers(), 'confirmed'],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        $user->forceFill(['role' => $data['role']])->save();

        \App\Models\SystemAuditLog::record('create_user', 'User', null, $data['name'], ['role' => $data['role']]);

        return redirect()->route('users.index')
            ->with('success', "User {$data['name']} created successfully.");
    }

    public function edit(int $id)
    {
        $user  = User::findOrFail($id);
        $roles = UserRole::cases();

        return view('users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        // Prevent the current admin from demoting themselves
        if ($user->id === auth()->id() && $request->role !== UserRole::Admin->value) {
            return back()->withErrors(['role' => 'You cannot change your own role.']);
        }

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role'  => ['required', Rule::enum(UserRole::class)],
            'password' => ['nullable', Password::min(8)->letters()->numbers(), 'confirmed'],
        ]);

        $user->name  = $data['name'];
        $user->email = $data['email'];
        $user->role  = $data['role'];

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        \App\Models\SystemAuditLog::record('update_user', 'User', $user->id, $user->name, ['role' => $data['role']]);

        return redirect()->route('users.index')
            ->with('success', "User {$user->name} updated successfully.");
    }

    public function destroy(int $id)
    {
        $user = User::findOrFail($id);

        // Prevent self-deletion
        if ($user->id === auth()->id()) {
            return back()->withErrors(['delete' => 'You cannot delete your own account.']);
        }

        $name = $user->name;
        $user->delete();

        \App\Models\SystemAuditLog::record('delete_user', 'User', $id, $name);

        return redirect()->route('users.index')
            ->with('success', "User {$name} deleted.");
    }
}
