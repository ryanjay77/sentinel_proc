@extends('layouts.app')

@section('title', 'SentinelProc - Risk Detection Logs')

@section('content')
    <div class="mb-6">
        <p class="text-sm uppercase tracking-[0.2em] text-cyan-400">Security</p>
        <h1 class="mt-2 text-3xl font-bold">Risk Detection Logs</h1>
        <p class="mt-1 text-sm text-slate-400">All processes that triggered a risk score — with score breakdowns and reasons.</p>
    </div>

    {{-- Summary cards --}}
    <div class="mb-6 grid grid-cols-4 gap-4">
        <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs uppercase tracking-wider text-slate-400">Total Detections</div>
            <div class="mt-2 text-2xl font-bold">{{ number_format($summary['total']) }}</div>
        </div>
        <div class="rounded-xl border border-red-500/20 bg-red-500/5 p-4">
            <div class="text-xs uppercase tracking-wider text-slate-400">High Risk</div>
            <div class="mt-2 text-2xl font-bold text-red-400">{{ number_format($summary['high']) }}</div>
        </div>
        <div class="rounded-xl border border-amber-500/20 bg-amber-500/5 p-4">
            <div class="text-xs uppercase tracking-wider text-slate-400">Medium Risk</div>
            <div class="mt-2 text-2xl font-bold text-amber-400">{{ number_format($summary['medium']) }}</div>
        </div>
        <div class="rounded-xl border border-purple-500/20 bg-purple-500/5 p-4">
            <div class="text-xs uppercase tracking-wider text-slate-400">VT Flagged</div>
            <div class="mt-2 text-2xl font-bold text-purple-400">{{ number_format($summary['vt']) }}</div>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('risk-detections.index') }}" class="mb-6 flex flex-wrap gap-3">
        <div class="flex-1 min-w-[200px]">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search process name..."
                class="w-full rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-cyan-500 focus:outline-none">
        </div>
        <select name="risk" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-cyan-500 focus:outline-none">
            <option value="">All Risk Levels</option>
            <option value="high"   {{ request('risk') === 'high'   ? 'selected' : '' }}>High</option>
            <option value="medium" {{ request('risk') === 'medium' ? 'selected' : '' }}>Medium</option>
        </select>
        <select name="event" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-cyan-500 focus:outline-none">
            <option value="">All Event Types</option>
            <option value="risk_change" {{ request('event') === 'risk_change' ? 'selected' : '' }}>Risk Change</option>
            <option value="vt_flagged"  {{ request('event') === 'vt_flagged'  ? 'selected' : '' }}>VT Flagged</option>
            <option value="first_seen"  {{ request('event') === 'first_seen'  ? 'selected' : '' }}>First Seen</option>
        </select>
        <button type="submit" class="rounded-lg bg-cyan-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-400">Filter</button>
        @if (request()->hasAny(['search','risk','event']))
            <a href="{{ route('risk-detections.index') }}" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800">Clear</a>
        @endif
    </form>

    @if ($logs->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-xl border border-slate-800 bg-slate-900 py-20 text-slate-500">
            <svg class="mb-4 h-14 w-14 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            @if (request()->hasAny(['search','risk','event']))
                <p class="text-lg font-medium">No detections match your filters.</p>
                <p class="mt-1 text-sm"><a href="{{ route('risk-detections.index') }}" class="text-cyan-400 hover:underline">Clear filters</a></p>
            @else
                <p class="text-lg font-medium">No risk detections yet.</p>
                <p class="mt-1 text-sm">Run a monitoring scan to populate this log.</p>
            @endif
        </div>
    @else
        <div class="space-y-3">
            @foreach ($logs as $log)
                @php
                    $d = $log->details ?? [];
                    $riskColor = match($log->risk_level) {
                        'high'   => 'bg-red-500/20 text-red-300',
                        'medium' => 'bg-amber-500/20 text-amber-300',
                        default  => 'bg-emerald-500/20 text-emerald-300',
                    };
                    $eventColor = match($log->event_type) {
                        'vt_flagged'  => 'bg-purple-500/20 text-purple-300',
                        'first_seen'  => 'bg-cyan-500/20 text-cyan-300',
                        default       => 'bg-slate-500/20 text-slate-300',
                    };
                @endphp
                <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-center gap-3 flex-wrap">
                            <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $riskColor }}">{{ strtoupper($log->risk_level ?? '—') }}</span>
                            <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $eventColor }}">{{ ucwords(str_replace('_', ' ', $log->event_type)) }}</span>
                            <span class="font-mono font-semibold">{{ $log->process_name }}</span>
                            <span class="text-xs text-slate-500">PID {{ $log->pid }}</span>
                        </div>
                        <div class="shrink-0 text-right">
                            <div class="text-2xl font-bold {{ $log->risk_level === 'high' ? 'text-red-400' : ($log->risk_level === 'medium' ? 'text-amber-400' : 'text-emerald-400') }}">
                                {{ $log->risk_score ?? 0 }}
                            </div>
                            <div class="text-xs text-slate-500">score</div>
                        </div>
                    </div>

                    @if (!empty($d['reasons']))
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($d['reasons'] as $reason)
                                <span class="rounded bg-slate-800 px-2 py-1 text-xs text-slate-300">{{ $reason }}</span>
                            @endforeach
                        </div>
                    @endif

                    @if ($log->path)
                        <div class="mt-2 truncate font-mono text-xs text-slate-500">{{ $log->path }}</div>
                    @endif

                    <div class="mt-2 text-xs text-slate-500">{{ $log->created_at->toDayDateTimeString() }}</div>
                </div>
            @endforeach
        </div>

        @if ($logs->hasPages())
            <div class="mt-8">{{ $logs->withQueryString()->links('pagination::tailwind') }}</div>
        @endif
    @endif
@endsection
