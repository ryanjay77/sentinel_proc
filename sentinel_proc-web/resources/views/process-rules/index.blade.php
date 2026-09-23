@extends('layouts.app')

@section('title', 'SentinelProc - Process Rules (Whitelist & Blacklist)')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <p class="text-sm uppercase tracking-[0.2em] text-cyan-400">Security Management</p>
            <h1 class="mt-2 text-3xl font-bold">Process Whitelist & Blacklist</h1>
            <p class="mt-1 text-sm text-slate-400">Manage rule conditions to allow trusted processes or block suspicious executables.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    {{-- Add New Rule Form --}}
    <div class="mb-8 rounded-xl border border-slate-800 bg-slate-900 p-6">
        <h2 class="mb-4 text-lg font-semibold text-slate-200">Add New Rule Condition</h2>
        <form method="POST" action="{{ route('rules.store') }}" class="grid grid-cols-1 gap-4 md:grid-cols-4">
            @csrf
            <div>
                <label for="process_name" class="mb-1 block text-xs uppercase text-slate-400">Process Name (.exe)</label>
                <input id="process_name" name="process_name" type="text" placeholder="e.g. svchost.exe or malware.exe" required
                    class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-cyan-500 focus:outline-none">
            </div>

            <div>
                <label for="type" class="mb-1 block text-xs uppercase text-slate-400">Rule Type</label>
                <select id="type" name="type" required
                    class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-cyan-500 focus:outline-none">
                    <option value="whitelist">Whitelist (Trusted)</option>
                    <option value="blacklist">Blacklist (Blocked/Untrusted)</option>
                </select>
            </div>

            <div>
                <label for="reason" class="mb-1 block text-xs uppercase text-slate-400">Reason / Description</label>
                <input id="reason" name="reason" type="text" placeholder="e.g. Known system binary"
                    class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-cyan-500 focus:outline-none">
            </div>

            <div class="flex items-end">
                <button type="submit" class="w-full rounded-lg bg-cyan-500 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-400 transition">
                    + Add Rule
                </button>
            </div>
        </form>
    </div>

    {{-- Tables Grid --}}
    <div class="grid grid-cols-1 gap-8 md:grid-cols-2">

        {{-- Whitelist Card --}}
        <div class="rounded-xl border border-emerald-500/20 bg-slate-900 p-5">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-emerald-400">Process Whitelist</h2>
                    <p class="text-xs text-slate-400">Processes permitted to run without triggering alerts.</p>
                </div>
                <span class="rounded-full bg-emerald-500/20 px-3 py-1 text-xs font-semibold text-emerald-300">
                    {{ $whitelist->count() }} Rules
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-800 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-3 px-2">Process Name</th>
                            <th class="py-3 px-2">Reason</th>
                            <th class="py-3 px-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($whitelist as $rule)
                            <tr class="hover:bg-slate-800/40">
                                <td class="py-3 px-2 font-mono font-medium text-emerald-300">{{ $rule->process_name }}</td>
                                <td class="py-3 px-2 text-xs text-slate-400">{{ $rule->reason ?? 'No description provided' }}</td>
                                <td class="py-3 px-2 text-right">
                                    <form method="POST" action="{{ route('rules.destroy', $rule->id) }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" onclick="return confirm('Remove rule for {{ $rule->process_name }}?')"
                                            class="text-xs text-red-400 hover:text-red-300">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-6 text-center text-xs text-slate-500">No whitelisted processes defined.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Blacklist Card --}}
        <div class="rounded-xl border border-red-500/20 bg-slate-900 p-5">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-red-400">Process Blacklist</h2>
                    <p class="text-xs text-slate-400">Flagged processes automatically triggering critical alerts.</p>
                </div>
                <span class="rounded-full bg-red-500/20 px-3 py-1 text-xs font-semibold text-red-300">
                    {{ $blacklist->count() }} Rules
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <tbody class="divide-y divide-slate-800">
                    <thead class="border-b border-slate-800 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-3 px-2">Process Name</th>
                            <th class="py-3 px-2">Reason</th>
                            <th class="py-3 px-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($blacklist as $rule)
                            <tr class="hover:bg-slate-800/40">
                                <td class="py-3 px-2 font-mono font-medium text-red-300">{{ $rule->process_name }}</td>
                                <td class="py-3 px-2 text-xs text-slate-400">{{ $rule->reason ?? 'No description provided' }}</td>
                                <td class="py-3 px-2 text-right">
                                    <form method="POST" action="{{ route('rules.destroy', $rule->id) }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" onclick="return confirm('Remove rule for {{ $rule->process_name }}?')"
                                            class="text-xs text-red-400 hover:text-red-300">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-6 text-center text-xs text-slate-500">No blacklisted processes defined.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
@endsection
