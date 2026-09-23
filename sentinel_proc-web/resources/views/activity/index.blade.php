@extends('layouts.app')

@section('title', 'SentinelProc - Process Activity Logs')

@section('content')
    <div class="mb-6">
        <p class="text-sm uppercase tracking-[0.2em] text-cyan-400">Monitoring</p>
        <h1 class="mt-2 text-3xl font-bold">Process Activity Logs</h1>
        <p class="mt-1 text-sm text-slate-400">Every process observed during each monitoring scan.</p>
    </div>

    {{-- Filter bar --}}
    <form method="GET" action="{{ route('activity.index') }}" class="mb-6 flex flex-wrap gap-3">
        <div class="flex-1 min-w-[200px]">
            <input type="text" name="search" value="{{ request('search') }}"
                placeholder="Search process name..."
                class="w-full rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-cyan-500 focus:outline-none">
        </div>
        <select name="event" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-cyan-500 focus:outline-none">
            <option value="">All Events</option>
            @foreach ($events as $e)
                <option value="{{ $e }}" {{ request('event') === $e ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $e)) }}</option>
            @endforeach
        </select>
        <select name="risk" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-cyan-500 focus:outline-none">
            <option value="">All Risk Levels</option>
            <option value="high"   {{ request('risk') === 'high'   ? 'selected' : '' }}>High</option>
            <option value="medium" {{ request('risk') === 'medium' ? 'selected' : '' }}>Medium</option>
            <option value="low"    {{ request('risk') === 'low'    ? 'selected' : '' }}>Low</option>
        </select>
        <button type="submit" class="rounded-lg bg-cyan-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-400">Filter</button>
        @if (request()->hasAny(['search','event','risk']))
            <a href="{{ route('activity.index') }}" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800">Clear</a>
        @endif
    </form>

    @if ($logs->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-xl border border-slate-800 bg-slate-900 py-20 text-slate-500">
            <svg class="mb-4 h-14 w-14 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            @if (request()->hasAny(['search','event','risk']))
                <p class="text-lg font-medium">No logs match your filters.</p>
                <p class="mt-1 text-sm"><a href="{{ route('activity.index') }}" class="text-cyan-400 hover:underline">Clear filters</a></p>
            @else
                <p class="text-lg font-medium">No activity logs yet.</p>
                <p class="mt-1 text-sm">Run a monitoring scan to populate this log.</p>
            @endif
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-slate-300">Time</th>
                        <th class="px-4 py-3 text-left text-slate-300">Process</th>
                        <th class="px-4 py-3 text-left text-slate-300">PID</th>
                        <th class="px-4 py-3 text-left text-slate-300">Event</th>
                        <th class="px-4 py-3 text-left text-slate-300">Risk</th>
                        <th class="px-4 py-3 text-left text-slate-300">Score</th>
                        <th class="px-4 py-3 text-left text-slate-300">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @foreach ($logs as $log)
                        @php
                            $eventColor = match($log->event_type) {
                                'vt_flagged'  => 'bg-red-500/20 text-red-300',
                                'risk_change' => 'bg-amber-500/20 text-amber-300',
                                'first_seen'  => 'bg-purple-500/20 text-purple-300',
                                default       => 'bg-slate-500/20 text-slate-300',
                            };
                            $riskColor = match($log->risk_level) {
                                'high'   => 'text-red-400',
                                'medium' => 'text-amber-400',
                                default  => 'text-emerald-400',
                            };
                        @endphp
                        <tr class="hover:bg-slate-800/60 transition">
                            <td class="px-4 py-3 text-xs text-slate-400 whitespace-nowrap">{{ $log->created_at->format('M d, H:i:s') }}</td>
                            <td class="px-4 py-3 font-mono font-medium">{{ $log->process_name }}</td>
                            <td class="px-4 py-3 font-mono text-slate-400">{{ $log->pid }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $eventColor }}">
                                    {{ ucwords(str_replace('_', ' ', $log->event_type)) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs font-semibold {{ $riskColor }}">{{ strtoupper($log->risk_level ?? '—') }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-400">{{ $log->risk_score ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs text-slate-400 max-w-xs truncate">
                                @php $d = $log->details; @endphp
                                @if (!empty($d['reasons']))
                                    {{ implode(', ', $d['reasons']) }}
                                @else
                                    —
                                @endif
                            </td>
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
