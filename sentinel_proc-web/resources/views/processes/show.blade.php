@extends('layouts.app')

@section('title', 'SentinelProc - ' . $process->name)

@section('content')
    <div class="mb-8">
        <a href="{{ route('processes.index') }}" class="text-sm text-cyan-400 hover:text-cyan-300">&larr; Back to Process Inventory</a>
        <div class="mt-4 flex items-start justify-between">
            <div>
                <p class="text-sm uppercase tracking-[0.2em] text-cyan-400">Inventory</p>
                <h1 class="mt-2 text-3xl font-bold font-mono">{{ $process->name }}</h1>
                <p class="mt-1 text-sm text-slate-400">PID {{ $process->pid }} &mdash; first seen {{ $process->first_seen ? \Carbon\Carbon::parse($process->first_seen)->diffForHumans() : 'unknown' }}</p>
            </div>
            <span class="rounded-full px-3 py-1 text-sm font-semibold
                {{ strtoupper($process->risk_level) === 'HIGH'
                    ? 'bg-red-500/20 text-red-300'
                    : (strtoupper($process->risk_level) === 'MEDIUM'
                        ? 'bg-amber-500/20 text-amber-300'
                        : 'bg-emerald-500/20 text-emerald-300') }}">
                {{ strtoupper($process->risk_level ?? 'NORMAL') }}
            </span>
        </div>
    </div>

    {{-- Stat cards --}}
    <div class="mb-6 grid grid-cols-4 gap-4">
        <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs uppercase tracking-wider text-slate-400">PID</div>
            <div class="mt-2 text-2xl font-bold font-mono">{{ $process->pid }}</div>
        </div>
        <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs uppercase tracking-wider text-slate-400">CPU Usage</div>
            <div class="mt-2 text-2xl font-bold">
                {{ $process->cpu_percent !== null ? number_format((float) $process->cpu_percent, 1).'%' : '—' }}
            </div>
        </div>
        <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs uppercase tracking-wider text-slate-400">Memory</div>
            <div class="mt-2 text-2xl font-bold">
                {{ $process->memory_mb !== null ? number_format((float) $process->memory_mb, 0).' MB' : '—' }}
            </div>
        </div>
        <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs uppercase tracking-wider text-slate-400">Alerts Triggered</div>
            <div class="mt-2 text-2xl font-bold {{ $process->alerts->count() > 0 ? 'text-red-400' : 'text-emerald-400' }}">
                {{ $process->alerts->count() }}
            </div>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-6">
        {{-- Main info --}}
        <div class="col-span-2 space-y-6">

            {{-- Process info --}}
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <h2 class="mb-4 text-lg font-semibold">Process Information</h2>
                <dl class="divide-y divide-slate-800">
                    <div class="flex justify-between py-3">
                        <dt class="text-sm text-slate-400">Process Name</dt>
                        <dd class="text-sm font-mono">{{ $process->name }}</dd>
                    </div>
                    <div class="flex justify-between py-3">
                        <dt class="text-sm text-slate-400">PID</dt>
                        <dd class="text-sm font-mono">{{ $process->pid }}</dd>
                    </div>
                    <div class="flex justify-between py-3">
                        <dt class="text-sm text-slate-400">Status</dt>
                        <dd class="text-sm font-mono">{{ $process->status ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between py-3">
                        <dt class="text-sm text-slate-400">Risk Level</dt>
                        <dd class="text-sm">{{ strtoupper($process->risk_level ?? 'NORMAL') }}</dd>
                    </div>
                    @if ($process->path)
                        <div class="py-3">
                            <dt class="text-sm text-slate-400 mb-1">Executable Path</dt>
                            <dd class="text-sm font-mono break-all text-slate-300">{{ $process->path }}</dd>
                        </div>
                    @endif
                    @if ($process->hash)
                        <div class="py-3">
                            <dt class="text-sm text-slate-400 mb-1">File Hash (SHA-256)</dt>
                            <dd class="text-xs font-mono break-all text-slate-300">{{ $process->hash }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between py-3">
                        <dt class="text-sm text-slate-400">First Seen</dt>
                        <dd class="text-sm">
                            {{ $process->first_seen ? \Carbon\Carbon::parse($process->first_seen)->toDayDateTimeString() : '—' }}
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- VirusTotal data --}}
            @if (! empty($process->virus_total_data))
                <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                    <h2 class="mb-4 text-lg font-semibold">VirusTotal Analysis</h2>

                    @php
                        $vt = $process->virus_total_data;
                        $malicious = $vt['malicious'] ?? $vt['positives'] ?? null;
                        $total = $vt['total'] ?? null;
                        $permalink = $vt['permalink'] ?? $vt['url'] ?? null;
                    @endphp

                    @if ($malicious !== null)
                        <div class="mb-4 flex items-center gap-3">
                            <span class="text-3xl font-bold {{ (int)$malicious > 0 ? 'text-red-400' : 'text-emerald-400' }}">
                                {{ $malicious }}{{ $total ? ' / '.$total : '' }}
                            </span>
                            <span class="text-sm text-slate-400">engines flagged this file</span>
                        </div>
                    @endif

                    <dl class="divide-y divide-slate-800">
                        @foreach ($vt as $key => $value)
                            @if (! is_array($value))
                                <div class="flex justify-between py-3">
                                    <dt class="text-sm text-slate-400">{{ ucwords(str_replace('_', ' ', $key)) }}</dt>
                                    <dd class="text-sm font-mono text-right max-w-xs break-all">{{ $value }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>

                    @if ($permalink)
                        <a href="{{ $permalink }}" target="_blank" rel="noopener"
                            class="mt-4 inline-block text-sm text-cyan-400 hover:text-cyan-300">
                            View on VirusTotal &rarr;
                        </a>
                    @endif
                </div>
            @endif

            {{-- Related alerts --}}
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <h2 class="mb-4 text-lg font-semibold">Related Alerts</h2>

                @if ($process->alerts->isEmpty())
                    <div class="flex flex-col items-center py-10 text-slate-500">
                        <svg class="mb-3 h-10 w-10 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-sm">No alerts triggered for this process.</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($process->alerts as $alert)
                            <a href="{{ route('alerts.show', $alert->id) }}"
                                class="block rounded-lg border border-slate-800 p-3 hover:border-slate-700 hover:bg-slate-800/50 transition">
                                <div class="flex items-center justify-between">
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold
                                        {{ strtoupper($alert->severity) === 'HIGH' || strtoupper($alert->severity) === 'CRITICAL'
                                            ? 'bg-red-500/20 text-red-300'
                                            : (strtoupper($alert->severity) === 'MEDIUM'
                                                ? 'bg-amber-500/20 text-amber-300'
                                                : 'bg-cyan-500/20 text-cyan-300') }}">
                                        {{ strtoupper($alert->severity) }}
                                    </span>
                                    <span class="text-xs text-slate-400">{{ $alert->created_at->diffForHumans() }}</span>
                                </div>
                                <div class="mt-2 text-sm font-medium">{{ $alert->message }}</div>
                                @if ($alert->acknowledged)
                                    <div class="mt-1 text-xs text-emerald-400">Acknowledged</div>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Snapshot info --}}
            @if ($process->snapshot)
                <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                    <h2 class="mb-4 text-lg font-semibold">Snapshot</h2>
                    <dl class="divide-y divide-slate-800 text-sm">
                        <div class="flex justify-between py-3">
                            <dt class="text-slate-400">Captured At</dt>
                            <dd>{{ \Carbon\Carbon::parse($process->snapshot->created_at)->toDayDateTimeString() }}</dd>
                        </div>
                    </dl>
                </div>
            @endif

            {{-- Risk summary card --}}
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <h2 class="mb-4 text-lg font-semibold">Risk Summary</h2>
                <div class="space-y-3 text-sm">
                    @php
                        $riskLevel = strtoupper($process->risk_level ?? 'NORMAL');
                    @endphp
                    <div class="rounded-lg p-3
                        {{ $riskLevel === 'HIGH'
                            ? 'border border-red-500/30 bg-red-500/10'
                            : ($riskLevel === 'MEDIUM'
                                ? 'border border-amber-500/30 bg-amber-500/10'
                                : 'border border-emerald-500/30 bg-emerald-500/10') }}">
                        <div class="font-semibold
                            {{ $riskLevel === 'HIGH' ? 'text-red-300' : ($riskLevel === 'MEDIUM' ? 'text-amber-300' : 'text-emerald-300') }}">
                            {{ $riskLevel }} RISK
                        </div>
                        <div class="mt-1 text-slate-400">
                            @if ($riskLevel === 'HIGH')
                                This process exhibits behaviour associated with threats. Immediate review recommended.
                            @elseif ($riskLevel === 'MEDIUM')
                                This process shows some suspicious indicators. Monitor closely.
                            @else
                                No significant threats detected for this process.
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
