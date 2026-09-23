@extends('layouts.app')

@section('title', 'SentinelProc - Processes')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <p class="text-sm uppercase tracking-[0.2em] text-cyan-400">Inventory</p>
            <h1 class="mt-2 text-3xl font-bold">Process Inventory</h1>
        </div>
    </div>

    {{-- Search & filter bar --}}
    <form method="GET" action="{{ route('processes.index') }}" class="mb-6 flex flex-wrap gap-3">
        <div class="flex-1 min-w-[200px]">
            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search by name, PID, user..."
                class="w-full rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-cyan-500 focus:outline-none"
            >
        </div>
        <select name="risk" class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-cyan-500 focus:outline-none">
            <option value="">All Risk Levels</option>
            <option value="High Risk"    {{ request('risk') === 'High Risk'    ? 'selected' : '' }}>High Risk</option>
            <option value="Medium Risk"  {{ request('risk') === 'Medium Risk'  ? 'selected' : '' }}>Medium Risk</option>
            <option value="Normal"       {{ request('risk') === 'Normal'       ? 'selected' : '' }}>Normal</option>
        </select>
        <button type="submit" class="rounded-lg bg-cyan-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-400">
            Filter
        </button>
        @if (request()->hasAny(['search', 'risk']))
            <a href="{{ route('processes.index') }}" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800">
                Clear
            </a>
        @endif
    </form>

    @php
        $isPaginated  = $processes instanceof \Illuminate\Pagination\LengthAwarePaginator
                     || $processes instanceof \Illuminate\Pagination\Paginator;
        $processItems = $isPaginated ? $processes->items() : $processes;

        // Client-side filter for array (snapshot/default) data
        if (! $isPaginated && request()->hasAny(['search', 'risk'])) {
            $search = strtolower(request('search', ''));
            $risk   = request('risk', '');

            $processItems = collect($processItems)->filter(function ($p) use ($search, $risk) {
                if ($search) {
                    $haystack = strtolower(($p['name'] ?? '').($p['pid'] ?? '').($p['user'] ?? ''));
                    if (! str_contains($haystack, $search)) return false;
                }
                if ($risk && ($p['status'] ?? '') !== $risk) return false;
                return true;
            })->values()->all();
        }
    @endphp

    @if (empty($processItems) && (! $isPaginated || $processes->isEmpty()))
        {{-- Empty state --}}
        <div class="flex flex-col items-center justify-center rounded-xl border border-slate-800 bg-slate-900 py-20 text-slate-500">
            <svg class="mb-4 h-14 w-14 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/>
            </svg>
            @if (request()->hasAny(['search', 'risk']))
                <p class="text-lg font-medium">No processes match your filters.</p>
                <p class="mt-1 text-sm">Try adjusting your search or <a href="{{ route('processes.index') }}" class="text-cyan-400 hover:underline">clear filters</a>.</p>
            @else
                <p class="text-lg font-medium">No processes recorded yet.</p>
                <p class="mt-1 text-sm">Run a monitoring scan to populate the process inventory.</p>
            @endif
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-slate-300">PID</th>
                        <th class="px-4 py-3 text-left text-slate-300">Process Name</th>
                        <th class="px-4 py-3 text-left text-slate-300">User</th>
                        <th class="px-4 py-3 text-left text-slate-300">CPU</th>
                        <th class="px-4 py-3 text-left text-slate-300">Memory</th>
                        <th class="px-4 py-3 text-left text-slate-300">Risk</th>
                        <th class="px-4 py-3 text-left text-slate-300"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @foreach ($processItems as $proc)
                        @php
                            $procId = is_array($proc) ? ($proc['id'] ?? null) : $proc->id;
                        @endphp
                        <tr class="bg-slate-900 hover:bg-slate-800/80 transition">
                            <td class="px-4 py-3 font-mono">{{ is_array($proc) ? $proc['pid'] : $proc->pid }}</td>
                            <td class="px-4 py-3 font-mono">{{ is_array($proc) ? $proc['name'] : $proc->name }}</td>
                            <td class="px-4 py-3">{{ is_array($proc) ? $proc['user'] : ($proc->status ?? 'unknown') }}</td>
                            <td class="px-4 py-3">{{ is_array($proc) ? $proc['cpu'] : (number_format((float)$proc->cpu_percent, 1).'%') }}</td>
                            <td class="px-4 py-3">{{ is_array($proc) ? $proc['memory'] : (number_format((float)$proc->memory_mb, 0).' MB') }}</td>
                            <td class="px-4 py-3">
                                @php
                                    $riskLabel = is_array($proc) ? $proc['status'] : match(strtoupper($proc->risk_level ?? '')) {
                                        'HIGH' => 'High Risk', 'MEDIUM' => 'Medium Risk', default => 'Normal'
                                    };
                                @endphp
                                <span class="rounded-full px-2 py-1 text-xs font-medium
                                    {{ $riskLabel === 'High Risk'
                                        ? 'bg-red-500/20 text-red-300'
                                        : ($riskLabel === 'Medium Risk'
                                            ? 'bg-amber-500/20 text-amber-300'
                                            : 'bg-emerald-500/20 text-emerald-300') }}">
                                    {{ $riskLabel }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if ($procId)
                                    <a href="{{ route('processes.show', $procId) }}"
                                        class="text-xs text-cyan-400 hover:text-cyan-300 whitespace-nowrap">
                                        Details &rarr;
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($isPaginated && $processes->hasPages())
            <div class="mt-8">
                {{ $processes->withQueryString()->links('pagination::tailwind') }}
            </div>
        @endif
    @endif
@endsection
