@extends('layouts.app')

@section('title', 'SentinelProc - Alert Detail')

@section('content')
    <div class="mb-8">
        <a href="{{ route('alerts.index') }}" class="text-sm text-cyan-400 hover:text-cyan-300">&larr; Back to Alert Logs</a>
        <div class="mt-4 flex items-start justify-between">
            <div>
                <p class="text-sm uppercase tracking-[0.2em] text-cyan-400">Security</p>
                <h1 class="mt-2 text-3xl font-bold">{{ $alert->message }}</h1>
                <p class="mt-1 text-sm text-slate-400">Detected {{ $alert->created_at->toDayDateTimeString() }}</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="rounded-full px-3 py-1 text-sm font-semibold
                    {{ strtoupper($alert->severity) === 'HIGH' || strtoupper($alert->severity) === 'CRITICAL'
                        ? 'bg-red-500/20 text-red-300'
                        : (strtoupper($alert->severity) === 'MEDIUM'
                            ? 'bg-amber-500/20 text-amber-300'
                            : 'bg-cyan-500/20 text-cyan-300') }}">
                    {{ strtoupper($alert->severity) }}
                </span>

                @if (! $alert->acknowledged && auth()->user()->canAcknowledgeAlerts())
                    <button id="acknowledgeBtn"
                        data-url="{{ route('alerts.acknowledge', $alert->id) }}"
                        class="rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-300 transition hover:bg-emerald-500/20">
                        Acknowledge
                    </button>
                @elseif ($alert->acknowledged)
                    <span class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-400">Acknowledged</span>
                @endif

                @if (auth()->user()->canDeleteAlerts())
                    <form method="POST" action="{{ route('alerts.destroy', $alert->id) }}"
                        onsubmit="return confirm('Permanently delete this alert?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-2 text-sm font-semibold text-red-300 transition hover:bg-red-500/20">
                            Delete Alert
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- Overview cards --}}
    <div class="mb-6 grid grid-cols-3 gap-4">
        <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs uppercase tracking-wider text-slate-400">Alert Type</div>
            <div class="mt-2 text-lg font-semibold">{{ $alert->alert_type ?? 'N/A' }}</div>
        </div>
        <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs uppercase tracking-wider text-slate-400">Severity</div>
            <div class="mt-2 text-lg font-semibold">{{ strtoupper($alert->severity) }}</div>
        </div>
        <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
            <div class="text-xs uppercase tracking-wider text-slate-400">Status</div>
            <div class="mt-2 text-lg font-semibold {{ $alert->acknowledged ? 'text-emerald-400' : 'text-amber-400' }}">
                {{ $alert->acknowledged ? 'Acknowledged' : 'Open' }}
            </div>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-6">
        {{-- Main detail panel --}}
        <div class="col-span-2 space-y-6">

            {{-- Alert details --}}
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <h2 class="mb-4 text-lg font-semibold">Alert Details</h2>
                <dl class="divide-y divide-slate-800">
                    <div class="flex justify-between py-3">
                        <dt class="text-sm text-slate-400">Message</dt>
                        <dd class="text-sm font-medium text-right max-w-xs break-all">{{ $alert->message }}</dd>
                    </div>
                    <div class="flex justify-between py-3">
                        <dt class="text-sm text-slate-400">Alert Type</dt>
                        <dd class="text-sm font-mono">{{ $alert->alert_type ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between py-3">
                        <dt class="text-sm text-slate-400">Detected At</dt>
                        <dd class="text-sm">{{ $alert->created_at->toDayDateTimeString() }}</dd>
                    </div>
                    <div class="flex justify-between py-3">
                        <dt class="text-sm text-slate-400">Acknowledged</dt>
                        <dd class="text-sm">{{ $alert->acknowledged ? 'Yes' : 'No' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Extra details from JSON field --}}
            @if (! empty($alert->details))
                <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                    <h2 class="mb-4 text-lg font-semibold">Additional Information</h2>
                    <dl class="divide-y divide-slate-800">
                        @foreach ($alert->details as $key => $value)
                            <div class="flex justify-between py-3">
                                <dt class="text-sm text-slate-400">{{ ucwords(str_replace('_', ' ', $key)) }}</dt>
                                <dd class="text-sm font-mono text-right max-w-xs break-all">
                                    {{ is_array($value) ? json_encode($value) : $value }}
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endif

        </div>

        {{-- Sidebar: linked process --}}
        <div class="space-y-6">
            @if ($alert->process)
                <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                    <h2 class="mb-4 text-lg font-semibold">Linked Process</h2>
                    <dl class="divide-y divide-slate-800 text-sm">
                        <div class="flex justify-between py-3">
                            <dt class="text-slate-400">Name</dt>
                            <dd class="font-mono">{{ $alert->process->name }}</dd>
                        </div>
                        <div class="flex justify-between py-3">
                            <dt class="text-slate-400">PID</dt>
                            <dd class="font-mono">{{ $alert->process->pid }}</dd>
                        </div>
                        <div class="flex justify-between py-3">
                            <dt class="text-slate-400">Risk Level</dt>
                            <dd>
                                <span class="rounded-full px-2 py-1 text-xs font-medium
                                    {{ strtoupper($alert->process->risk_level) === 'HIGH'
                                        ? 'bg-red-500/20 text-red-300'
                                        : (strtoupper($alert->process->risk_level) === 'MEDIUM'
                                            ? 'bg-amber-500/20 text-amber-300'
                                            : 'bg-emerald-500/20 text-emerald-300') }}">
                                    {{ strtoupper($alert->process->risk_level ?? 'NORMAL') }}
                                </span>
                            </dd>
                        </div>
                        @if ($alert->process->path)
                            <div class="py-3">
                                <dt class="text-slate-400 mb-1">Path</dt>
                                <dd class="break-all font-mono text-xs text-slate-300">{{ $alert->process->path }}</dd>
                            </div>
                        @endif
                    </dl>
                    <a href="{{ route('processes.show', $alert->process->id) }}"
                        class="mt-4 block text-center rounded-lg border border-cyan-500/40 bg-cyan-500/10 px-3 py-2 text-sm font-semibold text-cyan-300 hover:bg-cyan-500/20 transition">
                        View Full Process →
                    </a>
                </div>
            @else
                <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                    <h2 class="mb-2 text-lg font-semibold">Linked Process</h2>
                    <p class="text-sm text-slate-500">No process linked to this alert.</p>
                </div>
            @endif

            {{-- Snapshot info --}}
            @if ($alert->snapshot)
                <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                    <h2 class="mb-4 text-lg font-semibold">Snapshot</h2>
                    <dl class="divide-y divide-slate-800 text-sm">
                        <div class="flex justify-between py-3">
                            <dt class="text-slate-400">Captured At</dt>
                            <dd>{{ \Carbon\Carbon::parse($alert->snapshot->created_at)->toDayDateTimeString() }}</dd>
                        </div>
                    </dl>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const btn = document.getElementById('acknowledgeBtn');
            if (!btn) return;

            btn.addEventListener('click', async function () {
                btn.disabled = true;
                btn.textContent = 'Acknowledging...';

                try {
                    const response = await fetch(btn.dataset.url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({}),
                    });

                    if (!response.ok) throw new Error('Failed');

                    btn.textContent = 'Acknowledged';
                    btn.classList.remove('border-emerald-500/40', 'bg-emerald-500/10', 'text-emerald-300', 'hover:bg-emerald-500/20');
                    btn.classList.add('border-slate-700', 'bg-transparent', 'text-slate-400', 'cursor-default');
                } catch {
                    btn.disabled = false;
                    btn.textContent = 'Acknowledge';
                    alert('Could not acknowledge the alert.');
                }
            });
        });
    </script>
@endpush
