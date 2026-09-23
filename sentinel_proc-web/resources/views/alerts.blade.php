@extends('layouts.app')

@section('title', 'SentinelProc - Alerts')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <p class="text-sm uppercase tracking-[0.2em] text-cyan-400">Security</p>
            <h1 class="mt-2 text-3xl font-bold">Alert Logs</h1>
        </div>
    </div>

    {{-- Search & filter bar --}}
    <form method="GET" action="{{ route('alerts.index') }}" class="mb-6 flex flex-wrap gap-3">
        <div class="flex-1 min-w-[200px]">
            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search alerts..."
                class="w-full rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-cyan-500 focus:outline-none"
            >
        </div>
        <select name="severity" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-cyan-500 focus:outline-none">
            <option value="">All Severities</option>
            <option value="HIGH"   {{ request('severity') === 'HIGH'   ? 'selected' : '' }}>High</option>
            <option value="MEDIUM" {{ request('severity') === 'MEDIUM' ? 'selected' : '' }}>Medium</option>
            <option value="LOW"    {{ request('severity') === 'LOW'    ? 'selected' : '' }}>Low</option>
        </select>
        <select name="status" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-cyan-500 focus:outline-none">
            <option value="">All Statuses</option>
            <option value="open"         {{ request('status') === 'open'         ? 'selected' : '' }}>Open</option>
            <option value="acknowledged" {{ request('status') === 'acknowledged' ? 'selected' : '' }}>Acknowledged</option>
        </select>
        <button type="submit" class="rounded-lg bg-cyan-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-400">
            Filter
        </button>
        @if (request()->hasAny(['search', 'severity', 'status']))
            <a href="{{ route('alerts.index') }}" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800">
                Clear
            </a>
        @endif
    </form>

    @php
        $isPaginated = $alerts instanceof \Illuminate\Pagination\LengthAwarePaginator
                    || $alerts instanceof \Illuminate\Pagination\Paginator;
        $alertItems  = $isPaginated ? $alerts->items() : $alerts;

        // Client-side filter for array (snapshot/default) data
        if (! $isPaginated && request()->hasAny(['search', 'severity', 'status'])) {
            $search   = strtolower(request('search', ''));
            $severity = strtoupper(request('severity', ''));
            $status   = request('status', '');

            $alertItems = collect($alertItems)->filter(function ($a) use ($search, $severity, $status) {
                if ($search && ! str_contains(strtolower($a['title'] ?? ''), $search)
                           && ! str_contains(strtolower($a['source'] ?? ''), $search)) {
                    return false;
                }
                if ($severity && strtoupper($a['severity'] ?? '') !== $severity) {
                    return false;
                }
                if ($status === 'acknowledged' && empty($a['acknowledged'])) return false;
                if ($status === 'open' && ! empty($a['acknowledged'])) return false;
                return true;
            })->values()->all();
        }
    @endphp

    @if (empty($alertItems) && (! $isPaginated || $alerts->isEmpty()))
        {{-- Empty state --}}
        <div class="flex flex-col items-center justify-center rounded-xl border border-slate-800 bg-slate-900 py-20 text-slate-500">
            <svg class="mb-4 h-14 w-14 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            @if (request()->hasAny(['search', 'severity', 'status']))
                <p class="text-lg font-medium">No alerts match your filters.</p>
                <p class="mt-1 text-sm">Try adjusting your search or <a href="{{ route('alerts.index') }}" class="text-cyan-400 hover:underline">clear filters</a>.</p>
            @else
                <p class="text-lg font-medium">No alerts recorded yet.</p>
                <p class="mt-1 text-sm">Alerts will appear here once the monitoring agent detects suspicious activity.</p>
            @endif
        </div>
    @else
        <div class="space-y-4">
            @foreach ($alertItems as $alert)
                @php
                    $severity     = strtoupper(is_array($alert) ? $alert['severity']     : $alert->severity);
                    $title        = is_array($alert) ? $alert['title']        : $alert->message;
                    $time         = is_array($alert) ? $alert['time']         : $alert->created_at->diffForHumans();
                    $source       = is_array($alert) ? ($alert['source'] ?? '') : ($alert->process?->path ?? ($alert->details['source'] ?? 'Unknown'));
                    $acknowledged = is_array($alert) ? ($alert['acknowledged'] ?? false) : $alert->acknowledged;
                    $alertId      = is_array($alert) ? ($alert['id'] ?? null)  : $alert->id;
                @endphp
                <div class="rounded-xl border border-slate-800 bg-slate-900 p-5 {{ $acknowledged ? 'opacity-60' : '' }}">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="rounded-full px-2 py-1 text-xs font-semibold
                                {{ $severity === 'HIGH' || $severity === 'CRITICAL'
                                    ? 'bg-red-500/20 text-red-300'
                                    : ($severity === 'MEDIUM'
                                        ? 'bg-amber-500/20 text-amber-300'
                                        : 'bg-cyan-500/20 text-cyan-300') }}">
                                {{ $severity }}
                            </span>
                            @if ($alertId)
                                <a href="{{ route('alerts.show', $alertId) }}"
                                    class="text-lg font-semibold hover:text-cyan-300 transition">
                                    {{ $title }}
                                </a>
                            @else
                                <h2 class="text-lg font-semibold">{{ $title }}</h2>
                            @endif
                            @if ($acknowledged)
                                <span class="rounded-full bg-emerald-500/10 px-2 py-1 text-xs text-emerald-400">Acknowledged</span>
                            @endif
                        </div>
                        <span class="shrink-0 text-xs text-slate-400">{{ $time }}</span>
                    </div>
                    <div class="mt-3 break-all text-sm text-slate-300">{{ $source }}</div>
                    @if ($alertId)
                        <div class="mt-3">
                            <a href="{{ route('alerts.show', $alertId) }}"
                                class="text-xs text-cyan-400 hover:text-cyan-300">
                                View details &rarr;
                            </a>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if ($isPaginated && $alerts->hasPages())
            <div class="mt-8">
                {{ $alerts->withQueryString()->links('pagination::tailwind') }}
            </div>
        @endif
    @endif
@endsection
