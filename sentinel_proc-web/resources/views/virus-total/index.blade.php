@extends('layouts.app')

@section('title', 'SentinelProc - VirusTotal Lookup Results')

@section('content')
    <div class="mb-6">
        <p class="text-sm uppercase tracking-[0.2em] text-cyan-400">Threat Intelligence</p>
        <h1 class="mt-2 text-3xl font-bold">VirusTotal Lookup Results</h1>
        <p class="mt-1 text-sm text-slate-400">Hash reputation cache from all monitored executables.</p>
    </div>

    {{-- Summary cards --}}
    <div class="mb-6 grid grid-cols-4 gap-4">
        <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs uppercase tracking-wider text-slate-400">Total Hashes</div>
            <div class="mt-2 text-2xl font-bold">{{ number_format($summary['total']) }}</div>
        </div>
        <div class="rounded-xl border border-cyan-500/20 bg-cyan-500/5 p-4">
            <div class="text-xs uppercase tracking-wider text-slate-400">VT Checked</div>
            <div class="mt-2 text-2xl font-bold text-cyan-400">{{ number_format($summary['checked']) }}</div>
        </div>
        <div class="rounded-xl border border-red-500/20 bg-red-500/5 p-4">
            <div class="text-xs uppercase tracking-wider text-slate-400">Flagged</div>
            <div class="mt-2 text-2xl font-bold text-red-400">{{ number_format($summary['flagged']) }}</div>
        </div>
        <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-4">
            <div class="text-xs uppercase tracking-wider text-slate-400">Clean</div>
            <div class="mt-2 text-2xl font-bold text-emerald-400">{{ number_format($summary['clean']) }}</div>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('virus-total.index') }}" class="mb-6 flex flex-wrap gap-3">
        <div class="flex-1 min-w-[200px]">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search process name or hash..."
                class="w-full rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-cyan-500 focus:outline-none">
        </div>
        <label class="flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-300 cursor-pointer">
            <input type="checkbox" name="checked" value="1" {{ request('checked') ? 'checked' : '' }} class="rounded border-slate-600 bg-slate-800">
            VT Checked only
        </label>
        <label class="flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-300 cursor-pointer">
            <input type="checkbox" name="flagged" value="1" {{ request('flagged') ? 'checked' : '' }} class="rounded border-slate-600 bg-slate-800">
            Flagged only
        </label>
        <button type="submit" class="rounded-lg bg-cyan-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-400">Filter</button>
        @if (request()->hasAny(['search','checked','flagged']))
            <a href="{{ route('virus-total.index') }}" class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800">Clear</a>
        @endif
    </form>

    @if ($records->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-xl border border-slate-800 bg-slate-900 py-20 text-slate-500">
            <svg class="mb-4 h-14 w-14 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            @if (request()->hasAny(['search','checked','flagged']))
                <p class="text-lg font-medium">No results match your filters.</p>
                <p class="mt-1 text-sm"><a href="{{ route('virus-total.index') }}" class="text-cyan-400 hover:underline">Clear filters</a></p>
            @else
                <p class="text-lg font-medium">No hash records yet.</p>
                <p class="mt-1 text-sm">Run a monitoring scan to populate the hash cache.</p>
            @endif
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-slate-300">Process</th>
                        <th class="px-4 py-3 text-left text-slate-300">Hash (SHA-256)</th>
                        <th class="px-4 py-3 text-left text-slate-300">VT Result</th>
                        <th class="px-4 py-3 text-left text-slate-300">Checked At</th>
                        <th class="px-4 py-3 text-left text-slate-300">First Seen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @foreach ($records as $rec)
                        @php
                            $flagged   = ($rec->vt_malicious_count ?? 0) > 0;
                            $checked   = ! is_null($rec->vt_checked_at);
                        @endphp
                        <tr class="hover:bg-slate-800/60 transition {{ $flagged ? 'border-l-2 border-red-500' : '' }}">
                            <td class="px-4 py-3 font-mono font-medium">{{ $rec->process_name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="font-mono text-xs text-slate-400" title="{{ $rec->file_hash }}">
                                    {{ substr($rec->file_hash, 0, 16) }}…
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if (! $checked)
                                    <span class="text-xs text-slate-500">Not checked</span>
                                @elseif ($flagged)
                                    <span class="rounded-full bg-red-500/20 px-2 py-1 text-xs font-semibold text-red-300">
                                        {{ $rec->vt_malicious_count }}/{{ $rec->vt_total_engines }} flagged
                                    </span>
                                @else
                                    <span class="rounded-full bg-emerald-500/20 px-2 py-1 text-xs font-semibold text-emerald-300">
                                        Clean (0/{{ $rec->vt_total_engines }})
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-400">
                                {{ $rec->vt_checked_at ? \Carbon\Carbon::parse($rec->vt_checked_at)->diffForHumans() : '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-400">
                                {{ \Carbon\Carbon::parse($rec->created_at)->format('M d, Y') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($records->hasPages())
            <div class="mt-8">{{ $records->withQueryString()->links('pagination::tailwind') }}</div>
        @endif
    @endif
@endsection
