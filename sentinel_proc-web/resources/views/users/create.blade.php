@extends('layouts.app')

@section('title', 'SentinelProc - Add User')

@section('content')
    <div class="mb-6">
        <a href="{{ route('users.index') }}" class="text-sm text-cyan-400 hover:text-cyan-300">&larr; Back to Users</a>
        <div class="mt-4">
            <p class="text-sm uppercase tracking-[0.2em] text-cyan-400">Administration</p>
            <h1 class="mt-2 text-3xl font-bold">Add New User</h1>
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

            <form method="POST" action="{{ route('users.store') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="name" class="mb-1 block text-sm text-slate-400">Full Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus
                        class="w-full rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-slate-100 focus:border-cyan-500 focus:outline-none @error('name') border-red-500 @enderror">
                </div>

                <div>
                    <label for="email" class="mb-1 block text-sm text-slate-400">Email Address</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required
                        class="w-full rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-slate-100 focus:border-cyan-500 focus:outline-none @error('email') border-red-500 @enderror">
                </div>

                <div>
                    <label for="role" class="mb-1 block text-sm text-slate-400">Role</label>
                    <select id="role" name="role" required
                        class="w-full rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-slate-100 focus:border-cyan-500 focus:outline-none @error('role') border-red-500 @enderror">
                        <option value="">Select a role...</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}" {{ old('role') === $role->value ? 'selected' : '' }}>
                                {{ $role->label() }}
                            </option>
                        @endforeach
                    </select>
                    <div class="mt-2 space-y-1 text-xs text-slate-500">
                        <p><span class="text-red-300">Administrator</span> — full access including user management</p>
                        <p><span class="text-amber-300">Security Analyst</span> — operational actions (refresh, acknowledge, export)</p>
                        <p><span class="text-slate-300">Viewer</span> — read-only access to all data</p>
                    </div>
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm text-slate-400">Password</label>
                    <input id="password" name="password" type="password" required
                        class="w-full rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-slate-100 focus:border-cyan-500 focus:outline-none @error('password') border-red-500 @enderror">
                    <p class="mt-1 text-xs text-slate-500">Minimum 8 characters, must include letters and numbers.</p>
                </div>

                <div>
                    <label for="password_confirmation" class="mb-1 block text-sm text-slate-400">Confirm Password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required
                        class="w-full rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-slate-100 focus:border-cyan-500 focus:outline-none">
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit"
                        class="rounded-lg bg-cyan-500 px-5 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-400 transition">
                        Create User
                    </button>
                    <a href="{{ route('users.index') }}"
                        class="rounded-lg border border-slate-700 px-5 py-2 text-sm text-slate-300 hover:bg-slate-800 transition">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection
