@extends('layouts.app')

@section('title', 'SentinelProc - ' . $report['title'])

@section('content')
    <div class="mb-8 flex items-center justify-between">
        <div>
            <a href="{{ route('reports.index') }}" class="text-sm text-cyan-400 hover:text-cyan-300">&larr; Back to Reports</a>
            <h1 class="mt-2 text-3xl font-bold">{{ $report['title'] }}</h1>
            <p class="mt-1 text-sm text-slate-400">
                Generated {{ \Carbon\Carbon::parse($report['generated_at'])->toDayDateTimeString() }}
            </p>
        </div>
        @if (auth()->user()->canExportReports())
            <a href="{{ route('reports.export', $report['type']) }}"
                class="rounded-lg bg-cyan-500 px-4 py-2 font-semibold text-slate-950 hover:bg-cyan-400">
                Export CSV
            </a>
        @endif
    </div>

    <div class="mb-8 grid grid-cols-3 gap-4">
        @foreach ($report['overview'] as $label => $value)
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                <div class="text-sm text-slate-400">{{ ucwords(str_replace('_', ' ', $label)) }}</div>
                <div class="mt-2 text-3xl font-bold">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    @foreach ($report['sections'] as $section)
        <div class="mb-6 rounded-xl border border-slate-800 bg-slate-900 p-5">
            <h2 class="mb-4 text-xl font-semibold">{{ $section['heading'] }}</h2>
            <div class="overflow-hidden rounded-lg border border-slate-800">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-800 text-left text-slate-300">
                        <tr>
                            <th class="px-4 py-3">Label</th>
                            <th class="px-4 py-3">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($section['items'] as $item)
                            <tr class="border-t border-slate-800">
                                <td class="px-4 py-3">{{ $item['label'] }}</td>
                                <td class="px-4 py-3 font-mono text-xs">{{ $item['value'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach

    @if (! empty($report['processes']))
        <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
            <h2 class="mb-4 text-xl font-semibold">Monitored Processes</h2>
            <div class="overflow-hidden rounded-lg border border-slate-800">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-800 text-left text-slate-300">
                        <tr>
                            <th class="px-4 py-3">PID</th>
                            <th class="px-4 py-3">Process</th>
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3">CPU</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report['processes'] as $proc)
                            <tr class="border-t border-slate-800">
                                <td class="px-4 py-3">{{ $proc['pid'] }}</td>
                                <td class="px-4 py-3">{{ $proc['name'] }}</td>
                                <td class="px-4 py-3">{{ $proc['user'] }}</td>
                                <td class="px-4 py-3">{{ $proc['cpu'] }}</td>
                                <td class="px-4 py-3">{{ $proc['status'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if (! empty($report['alerts']))
        <div class="mt-6 rounded-xl border border-slate-800 bg-slate-900 p-5">
            <h2 class="mb-4 text-xl font-semibold">Related Alerts</h2>
            <div class="space-y-3">
                @foreach ($report['alerts'] as $alert)
                    <div class="rounded-lg border border-slate-800 p-3">
                        <div class="flex items-center justify-between">
                            <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $alert['severity'] === 'HIGH' ? 'bg-red-500/20 text-red-300' : ($alert['severity'] === 'MEDIUM' ? 'bg-amber-500/20 text-amber-300' : 'bg-cyan-500/20 text-cyan-300') }}">
                                {{ $alert['severity'] }}
                            </span>
                            <span class="text-xs text-slate-400">{{ $alert['time'] ?? '' }}</span>
                        </div>
                        <div class="mt-2 text-sm font-medium">{{ $alert['title'] }}</div>
                        <div class="mt-1 break-all text-xs text-slate-400">{{ $alert['source'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endsection
