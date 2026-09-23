@extends('layouts.app')

@section('title', 'SentinelProc - Edit User')

@section('content')
    <div class="mb-6">
        <a href="{{ route('users.index') }}" class="text-sm text-cyan-400 hover:text-cyan-300">&larr; Back to Users</a>
        <div class="mt-4">
            <p class="text-sm uppercase tracking-[0.2em] text-cyan-400">Administration</p>
            <h1 class="mt-2 text-3xl font-bold">Edit User</h1>
            <p class="mt-1 text-sm text-slate-400">{{ $user->email }}</p>
        </div>
    </div>

    <div class="max-w-xl">
        <div class="rounded-xl border border-slate-800 bg-slate-900 p-6">
            @if ($errors->any())
                <div class="mb-5 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
                    <ul class="space-y-1 list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('users.update', $user->id) }}" class="space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <label for="name" class="mb-1 block text-sm text-slate-400">Full Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required autofocus
                        class="w-full rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-slate-100 focus:border-cyan-500 focus:outline-none @error('name') border-red-500 @enderror">
                </div>

                <div>
                    <label for="email" class="mb-1 block text-sm text-slate-400">Email Address</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required
                        class="w-full rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-slate-100 focus:border-cyan-500 focus:outline-none @error('email') border-red-500 @enderror">
                </div>

                <div>
                    <label for="role" class="mb-1 block text-sm text-slate-400">Role</label>
                    <select id="role" name="role" required
                        class="w-full rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-slate-100 focus:border-cyan-500 focus:outline-none @error('role') border-red-500 @enderror"
                        {{ $user->id === auth()->id() ? 'disabled' : '' }}>
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}"
                                {{ old('role', $user->role->value) === $role->value ? 'selected' : '' }}>
                                {{ $role->label() }}
                            </option>
                        @endforeach
                    </select>
                    {{-- If disabled, still submit the value --}}
                    @if ($user->id === auth()->id())
                        <input type="hidden" name="role" value="{{ $user->role->value }}">
                        <p class="mt-1 text-xs text-amber-400">You cannot change your own role.</p>
                    @endif
                </div>

                <div class="rounded-lg border border-slate-700 bg-slate-800/50 p-4">
                    <p class="mb-3 text-sm font-medium text-slate-300">Change Password</p>
                    <div class="space-y-4">
                        <div>
                            <label for="password" class="mb-1 block text-sm text-slate-400">New Password <span class="text-slate-500">(leave blank to keep current)</span></label>
                            <input id="password" name="password" type="password"
                                class="w-full rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-slate-100 focus:border-cyan-500 focus:outline-none @error('password') border-red-500 @enderror">
                        </div>
                        <div>
                            <label for="password_confirmation" class="mb-1 block text-sm text-slate-400">Confirm New Password</label>
                            <input id="password_confirmation" name="password_confirmation" type="password"
                                class="w-full rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-slate-100 focus:border-cyan-500 focus:outline-none">
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit"
                        class="rounded-lg bg-cyan-500 px-5 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-400 transition">
                        Save Changes
                    </button>
                    <a href="{{ route('users.index') }}"
                        class="rounded-lg border border-slate-700 px-5 py-2 text-sm text-slate-300 hover:bg-slate-800 transition">
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        {{-- Danger zone --}}
        @if ($user->id !== auth()->id())
            <div class="mt-6 rounded-xl border border-red-500/20 bg-red-500/5 p-5">
                <h2 class="mb-1 text-sm font-semibold text-red-300">Danger Zone</h2>
                <p class="mb-4 text-sm text-slate-400">Permanently delete this user. This action cannot be undone.</p>
                <form method="POST" action="{{ route('users.destroy', $user->id) }}"
                    onsubmit="return confirm('Permanently delete {{ addslashes($user->name) }}?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-2 text-sm font-semibold text-red-300 hover:bg-red-500/20 transition">
                        Delete User
                    </button>
                </form>
            </div>
        @endif
    </div>
@endsection
