@extends('layouts.app')

@section('title', 'SentinelProc - System Activity Logs')

@section('content')
    <div class="mb-6">
        <p class="text-sm uppercase tracking-[0.2em] text-cyan-400">Administration</p>
        <h1 class="mt-2 text-3xl font-bold">System Activity Logs</h1>
        <p class="mt-1 text-sm text-slate-400">Audit trail of all user actions performed in the system.</p>
    </div>

    {{-- Filter bar --}}
    <form method="GET" action="{{ route('activity.system') }}" class="mb-6 flex flex-wrap gap-3">
        <div class="flex-1 min-w-[200px]">
            <input type="text" name="search" value="{{ request('search') }}"
                placeholder="Search user, action, target..."
                class="w-full rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-cyan-500 focus:outline-none">
        </div>
        <select name="action" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-cyan-500 focus:outline-none">
            <option value="">All Actions</option>
            @foreach ($actions as $a)
                <option value="{{ $a }}" {{ request('action') === $a ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $a)) }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-lg bg-cyan-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-400">Filter</button>
        @if (request()->hasAny(['search','action']))
            <a href="{{ route('activity.system') }}" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800">Clear</a>
        @endif
    </form>

    @if ($logs->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-xl border border-slate-800 bg-slate-900 py-20 text-slate-500">
            <svg class="mb-4 h-14 w-14 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            @if (request()->hasAny(['search','action']))
                <p class="text-lg font-medium">No logs match your filters.</p>
                <p class="mt-1 text-sm"><a href="{{ route('activity.system') }}" class="text-cyan-400 hover:underline">Clear filters</a></p>
            @else
                <p class="text-lg font-medium">No system activity recorded yet.</p>
                <p class="mt-1 text-sm">Actions like logins, acknowledges, and exports will appear here.</p>
            @endif
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-slate-300">Time</th>
                        <th class="px-4 py-3 text-left text-slate-300">User</th>
                        <th class="px-4 py-3 text-left text-slate-300">Action</th>
                        <th class="px-4 py-3 text-left text-slate-300">Target</th>
                        <th class="px-4 py-3 text-left text-slate-300">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @foreach ($logs as $log)
                        @php
                            $actionColor = match(true) {
                                str_contains($log->action, 'delete') => 'bg-red-500/20 text-red-300',
                                str_contains($log->action, 'create') || str_contains($log->action, 'update') => 'bg-cyan-500/20 text-cyan-300',
                                str_contains($log->action, 'login') || str_contains($log->action, 'logout') => 'bg-purple-500/20 text-purple-300',
                                str_contains($log->action, 'export') => 'bg-emerald-500/20 text-emerald-300',
                                default => 'bg-slate-500/20 text-slate-300',
                            };
                        @endphp
                        <tr class="hover:bg-slate-800/60 transition">
                            <td class="px-4 py-3 text-xs text-slate-400 whitespace-nowrap">{{ $log->created_at->format('M d, Y H:i:s') }}</td>
                            <td class="px-4 py-3 font-medium">{{ $log->user_name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $actionColor }}">
                                    {{ ucwords(str_replace('_', ' ', $log->action)) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-300">
                                @if ($log->target_type)
                                    <span class="text-slate-500 text-xs">{{ $log->target_type }}</span>
                                    @if ($log->target_label)
                                        <span class="ml-1">{{ Str::limit($log->target_label, 50) }}</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-400">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="mt-8">{{ $logs->withQueryString()->links('pagination::tailwind') }}</div>
        @endif
    @endif
@endsection
