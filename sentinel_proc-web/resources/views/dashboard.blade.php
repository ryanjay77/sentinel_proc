@extends('layouts.app')

@section('title', 'SentinelProc Dashboard')

@section('content')
    <div class="mb-8 flex items-center justify-between">
        <div>
            <div class="text-xs uppercase tracking-[0.3em] text-cyan-400">Real-time Monitoring</div>
            <h1 class="mt-2 text-3xl font-bold">Process Monitoring Dashboard</h1>
        </div>
        <div class="flex items-center gap-3">
            @if (count($hostnames) > 0)
                <form method="GET" action="{{ route('dashboard') }}" class="flex items-center gap-2">
                    <label for="hostnameFilter" class="text-xs font-medium uppercase tracking-wider text-slate-400">Laptop</label>
                    <select id="hostnameFilter" name="hostname" onchange="this.form.submit()"
                            class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-200 transition focus:border-cyan-500 focus:outline-none">
                        <option value="">All laptops</option>
                        @foreach ($hostnames as $host)
                            <option value="{{ $host }}" @selected($hostnameFilter === $host)>{{ $host }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
            @if (auth()->user()->canRefresh())
                <button id="refreshSnapshot" type="button" class="rounded-lg border border-cyan-500/40 bg-cyan-500/10 px-4 py-2 font-semibold text-cyan-300 transition hover:bg-cyan-500/20">Refresh Snapshot</button>
            @endif
            @if (auth()->user()->canExportReports())
                <a href="{{ route('reports.export', 'risk-summary') }}" class="rounded-lg bg-cyan-500 px-4 py-2 font-semibold text-slate-950">Export Report</a>
            @endif
        </div>
    </div>

    <section class="mb-8 rounded-xl border border-slate-800 bg-slate-900 p-5" aria-labelledby="agents-heading">
        <div class="mb-4 flex items-center justify-between">
            <h2 id="agents-heading" class="text-lg font-semibold">Monitored laptops</h2>
            <span class="text-xs text-slate-400">Online when seen within 30 seconds</span>
        </div>
        @forelse ($agents as $agent)
            <a href="{{ route('dashboard', ['hostname' => $agent['hostname']]) }}"
               class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-800 py-3 first:border-t-0 {{ $hostnameFilter === $agent['hostname'] ? 'text-cyan-300' : 'text-slate-200' }}">
                <span class="font-medium">{{ $agent['hostname'] }}</span>
                <span class="text-xs text-slate-400">{{ $agent['ip_address'] ?: 'IP unavailable' }}</span>
                <span class="text-xs {{ $agent['is_online'] ? 'text-emerald-400' : 'text-slate-500' }}">
                    {{ $agent['is_online'] ? 'Online' : 'Offline' }}
                    @if ($agent['last_seen'])
                        <span class="text-slate-500">· {{ $agent['last_seen']->diffForHumans() }}</span>
                    @endif
                </span>
            </a>
        @empty
            <p class="border-t border-slate-800 pt-3 text-sm text-slate-500">No laptops have reported yet.</p>
        @endforelse
    </section>

    <div class="mb-8 grid grid-cols-4 gap-4">
        @foreach ($stats as $stat)
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                <div class="text-sm text-slate-400">{{ $stat['label'] }}</div>
                <div class="mt-3 flex items-end justify-between">
                    <div class="text-3xl font-bold">{{ $stat['value'] }}</div>
                    <div class="text-xs {{ $stat['tone'] === 'danger' ? 'text-red-400' : ($stat['tone'] === 'warning' ? 'text-amber-400' : ($stat['tone'] === 'success' ? 'text-emerald-400' : 'text-cyan-400')) }}">{{ $stat['trend'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mb-8 grid grid-cols-2 gap-6">
        <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-xl font-semibold">Process Inventory by Risk</h2>
                <span class="text-xs text-slate-400">{{ array_sum($analytics['riskLevels']['values']) }} tracked</span>
            </div>
            @if (array_sum($analytics['riskLevels']['values']) > 0)
                <div class="relative h-64">
                    <canvas id="riskLevelChart"></canvas>
                </div>
            @else
                <div class="flex h-64 items-center justify-center text-sm text-slate-500">No process data yet.</div>
            @endif
        </div>

        <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-xl font-semibold">Alert Log by Severity</h2>
                <span class="text-xs text-slate-400">{{ array_sum($analytics['severities']['values']) }} alerts</span>
            </div>
            @if (array_sum($analytics['severities']['values']) > 0)
                <div class="relative h-64">
                    <canvas id="alertSeverityChart"></canvas>
                </div>
            @else
                <div class="flex h-64 items-center justify-center text-sm text-slate-500">No alert data yet.</div>
            @endif
        </div>

        <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-xl font-semibold">New Processes Discovered per Day</h2>
                <span class="text-xs text-slate-400">Last {{ count($analytics['discoveries']['labels']) }} active days</span>
            </div>
            @if (count($analytics['discoveries']['values']) > 0)
                <div class="relative h-64">
                    <canvas id="discoveriesChart"></canvas>
                </div>
            @else
                <div class="flex h-64 items-center justify-center text-sm text-slate-500">No discovery history yet.</div>
            @endif
        </div>

        <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-xl font-semibold">Top 5 Memory Consumers</h2>
                <span class="text-xs text-slate-400">Latest scan</span>
            </div>
            @if (count($analytics['consumers']['values']) > 0)
                <div class="relative h-64">
                    <canvas id="memoryConsumersChart"></canvas>
                </div>
            @else
                <div class="flex h-64 items-center justify-center text-sm text-slate-500">No process data yet.</div>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-3 gap-6">
        <div class="col-span-2 rounded-xl border border-slate-800 bg-slate-900 p-5">
            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-semibold">Live Process Activity</h2>
                    @if ($snapshotHostname)
                        <span class="rounded-full border border-cyan-500/40 bg-cyan-500/10 px-2 py-1 text-xs font-medium text-cyan-300">{{ $snapshotHostname }}</span>
                    @endif
                </div>
                <span class="text-xs text-slate-400">{{ $generatedAt ? 'Updated ' . \Carbon\Carbon::parse($generatedAt)->diffForHumans() : 'Updated 12s ago' }}</span>
            </div>

            <div class="overflow-hidden rounded-lg border border-slate-800">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-800 text-left text-slate-300">
                        <tr>
                            <th class="px-4 py-3">PID</th>
                            <th class="px-4 py-3">Process</th>
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3">CPU</th>
                            <th class="px-4 py-3">Memory</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($processes as $proc)
                            <tr class="border-t border-slate-800 bg-slate-900 hover:bg-slate-800/80">
                                <td class="px-4 py-3">{{ $proc['pid'] }}</td>
                                <td class="px-4 py-3">{{ $proc['name'] }}</td>
                                <td class="px-4 py-3">{{ $proc['user'] }}</td>
                                <td class="px-4 py-3">{{ $proc['cpu'] }}</td>
                                <td class="px-4 py-3">{{ $proc['memory'] }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs font-medium {{ $proc['status'] === 'High Risk' ? 'bg-red-500/20 text-red-300' : ($proc['status'] === 'Medium Risk' ? 'bg-amber-500/20 text-amber-300' : 'bg-emerald-500/20 text-emerald-300') }}">
                                        {{ $proc['status'] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">
                                    {{ $hostnameFilter ? "No scans from {$hostnameFilter} yet." : 'No process data yet.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
            <h2 class="mb-4 text-xl font-semibold">Security Alerts</h2>
            <div class="space-y-3">
                @forelse ($alerts as $alert)
                    <div class="rounded-lg border border-slate-800 p-3">
                        <div class="flex items-center justify-between">
                            <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $alert['severity'] === 'HIGH' ? 'bg-red-500/20 text-red-300' : ($alert['severity'] === 'MEDIUM' ? 'bg-amber-500/20 text-amber-300' : 'bg-cyan-500/20 text-cyan-300') }}">
                                {{ $alert['severity'] }}
                            </span>
                            <span class="text-xs text-slate-400">{{ $alert['time'] }}</span>
                        </div>
                        <div class="mt-2 text-sm font-medium">{{ $alert['title'] }}</div>
                        <div class="mt-1 break-all text-xs text-slate-400">{{ $alert['source'] }}</div>
                    </div>
                @empty
                    <div class="rounded-lg border border-slate-800 p-3 text-sm text-slate-500">No alerts to show.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Chart === 'undefined') return;

            const analytics = @json($analytics, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            const tickColor = '#94a3b8';
            const gridColor = 'rgba(148, 163, 184, 0.15)';
            const legendLabels = { color: '#cbd5e1', padding: 14, boxWidth: 12 };

            const riskCanvas = document.getElementById('riskLevelChart');
            if (riskCanvas) {
                new Chart(riskCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: analytics.riskLevels.labels,
                        datasets: [{
                            data: analytics.riskLevels.values,
                            backgroundColor: ['#ef4444', '#f59e0b', '#10b981'],
                            borderColor: '#0f172a',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        cutout: '62%',
                        plugins: { legend: { position: 'bottom', labels: legendLabels } }
                    }
                });
            }

            const severityCanvas = document.getElementById('alertSeverityChart');
            if (severityCanvas) {
                new Chart(severityCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: analytics.severities.labels,
                        datasets: [{
                            data: analytics.severities.values,
                            backgroundColor: ['#dc2626', '#ef4444', '#f59e0b', '#22d3ee'],
                            borderColor: '#0f172a',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        cutout: '62%',
                        plugins: { legend: { position: 'bottom', labels: legendLabels } }
                    }
                });
            }

            const discoveriesCanvas = document.getElementById('discoveriesChart');
            if (discoveriesCanvas) {
                new Chart(discoveriesCanvas, {
                    type: 'bar',
                    data: {
                        labels: analytics.discoveries.labels,
                        datasets: [{
                            label: 'New hashes',
                            data: analytics.discoveries.values,
                            backgroundColor: '#22d3ee',
                            borderRadius: 4
                        }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { ticks: { color: tickColor }, grid: { display: false } },
                            y: {
                                beginAtZero: true,
                                ticks: { color: tickColor, precision: 0 },
                                grid: { color: gridColor }
                            }
                        }
                    }
                });
            }

            const memoryCanvas = document.getElementById('memoryConsumersChart');
            if (memoryCanvas) {
                new Chart(memoryCanvas, {
                    type: 'bar',
                    data: {
                        labels: analytics.consumers.labels,
                        datasets: [{
                            label: 'Memory (MB)',
                            data: analytics.consumers.values,
                            backgroundColor: '#a78bfa',
                            borderRadius: 4
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: (context) => context.parsed.x + ' MB'
                                }
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: { color: tickColor },
                                grid: { color: gridColor }
                            },
                            y: { ticks: { color: tickColor }, grid: { display: false } }
                        }
                    }
                });
            }
        });
    </script>

    @if (auth()->user()->canRefresh())
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const button = document.getElementById('refreshSnapshot');
                if (!button) return;

                button.addEventListener('click', async function () {
                    button.disabled = true;
                    button.textContent = 'Scanning...';

                    try {
                        const response = await fetch('{{ route('monitoring.refresh') }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({})
                        });

                        if (!response.ok) {
                            throw new Error('Refresh failed');
                        }

                        // Scan runs in the background — wait for it to finish
                        // then reload. A full scan takes ~15-30 seconds.
                        button.textContent = 'Scan running… (30s)';
                        let countdown = 30;
                        const timer = setInterval(function () {
                            countdown--;
                            button.textContent = 'Scan running… (' + countdown + 's)';
                            if (countdown <= 0) {
                                clearInterval(timer);
                                window.location.reload();
                            }
                        }, 1000);

                    } catch (error) {
                        button.disabled = false;
                        button.textContent = 'Refresh Snapshot';
                        alert('Could not refresh the monitoring snapshot.');
                    }
                });
            });
        </script>
    @endif
@endpush
