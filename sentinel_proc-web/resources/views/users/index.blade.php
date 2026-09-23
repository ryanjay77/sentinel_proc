@extends('layouts.app')

@section('title', 'SentinelProc - User Management')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <p class="text-sm uppercase tracking-[0.2em] text-cyan-400">Administration</p>
            <h1 class="mt-2 text-3xl font-bold">User Management</h1>
        </div>
        <a href="{{ route('users.create') }}"
            class="rounded-lg bg-cyan-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-400 transition">
            + Add User
        </a>
    </div>

    {{-- Flash messages --}}
    @if (session('success'))
        <div class="mb-5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->has('delete'))
        <div class="mb-5 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
            {{ $errors->first('delete') }}
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900">
        <table class="min-w-full divide-y divide-slate-800 text-sm">
            <thead class="bg-slate-800">
                <tr>
                    <th class="px-5 py-3 text-left text-slate-300">Name</th>
                    <th class="px-5 py-3 text-left text-slate-300">Email</th>
                    <th class="px-5 py-3 text-left text-slate-300">Role</th>
                    <th class="px-5 py-3 text-left text-slate-300">Joined</th>
                    <th class="px-5 py-3 text-left text-slate-300">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse ($users as $user)
                    <tr class="bg-slate-900 hover:bg-slate-800/60 transition {{ $user->id === auth()->id() ? 'opacity-90' : '' }}">
                        <td class="px-5 py-3 font-medium">
                            {{ $user->name }}
                            @if ($user->id === auth()->id())
                                <span class="ml-2 rounded-full bg-cyan-500/10 px-2 py-0.5 text-xs text-cyan-400">You</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-slate-300">{{ $user->email }}</td>
                        <td class="px-5 py-3">
                            @php
                                $roleLabel = $user->role->label();
                                $roleColor = match($user->role) {
                                    \App\Enums\UserRole::Admin    => 'bg-red-500/20 text-red-300',
                                    \App\Enums\UserRole::Analyst  => 'bg-amber-500/20 text-amber-300',
                                    \App\Enums\UserRole::Viewer   => 'bg-slate-500/20 text-slate-300',
                                };
                            @endphp
                            <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $roleColor }}">
                                {{ $roleLabel }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-slate-400">{{ $user->created_at->toDateString() }}</td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <a href="{{ route('users.edit', $user->id) }}"
                                    class="text-xs text-cyan-400 hover:text-cyan-300 transition">
                                    Edit
                                </a>
                                @if ($user->id !== auth()->id())
                                    <form method="POST" action="{{ route('users.destroy', $user->id) }}"
                                        onsubmit="return confirm('Delete {{ addslashes($user->name) }}? This cannot be undone.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="text-xs text-red-400 hover:text-red-300 transition">
                                            Delete
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-16 text-center text-slate-500">
                            <p class="text-lg font-medium">No users found.</p>
                            <p class="mt-1 text-sm">
                                <a href="{{ route('users.create') }}" class="text-cyan-400 hover:underline">Add the first user</a>
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Role legend --}}
    <div class="mt-6 rounded-xl border border-slate-800 bg-slate-900 p-5">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wider text-slate-400">Role Permissions</h2>
        <div class="grid grid-cols-3 gap-4 text-sm">
            <div class="rounded-lg border border-red-500/20 bg-red-500/5 p-4">
                <div class="mb-2 font-semibold text-red-300">Administrator</div>
                <ul class="space-y-1 text-slate-400">
                    <li>✓ Manage users (add, edit, delete)</li>
                    <li>✓ Delete alerts</li>
                    <li>✓ Refresh monitoring snapshot</li>
                    <li>✓ Acknowledge alerts</li>
                    <li>✓ Export reports</li>
                    <li>✓ View all data</li>
                </ul>
            </div>
            <div class="rounded-lg border border-amber-500/20 bg-amber-500/5 p-4">
                <div class="mb-2 font-semibold text-amber-300">Security Analyst</div>
                <ul class="space-y-1 text-slate-400">
                    <li>✗ Cannot manage users</li>
                    <li>✗ Cannot delete alerts</li>
                    <li>✓ Refresh monitoring snapshot</li>
                    <li>✓ Acknowledge alerts</li>
                    <li>✓ Export reports</li>
                    <li>✓ View all data</li>
                </ul>
            </div>
            <div class="rounded-lg border border-slate-600/30 bg-slate-800/30 p-4">
                <div class="mb-2 font-semibold text-slate-300">Viewer</div>
                <ul class="space-y-1 text-slate-400">
                    <li>✗ Cannot manage users</li>
                    <li>✗ Cannot delete alerts</li>
                    <li>✗ Cannot refresh snapshot</li>
                    <li>✗ Cannot acknowledge alerts</li>
                    <li>✗ Cannot export reports</li>
                    <li>✓ View all data</li>
                </ul>
            </div>
        </div>
    </div>
@endsection
