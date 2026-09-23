@extends('layouts.app')

@section('title', 'SentinelProc - Reports')

@section('content')
    <div class="mb-8 flex items-center justify-between">
        <div>
            <p class="text-sm uppercase tracking-[0.2em] text-cyan-400">Reporting</p>
            <h1 class="mt-2 text-3xl font-bold">Threat Intelligence Reports</h1>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-5">
        @foreach ($reports as $report)
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <div class="text-xs uppercase tracking-[0.2em] text-cyan-400">{{ $report['category'] }}</div>
                <div class="mt-3 text-xl font-semibold">{{ $report['title'] }}</div>
                <div class="mt-2 text-sm text-slate-400">{{ $report['range'] }}</div>
                <p class="mt-3 text-sm text-slate-300">{{ $report['summary'] }}</p>
                <div class="mt-5 flex gap-2">
                    <a href="{{ route('reports.show', $report['slug']) }}"
                        class="rounded-lg bg-cyan-500 px-3 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-400">
                        View Report
                    </a>
                    @if (auth()->user()->canExportReports())
                        <a href="{{ route('reports.export', $report['slug']) }}"
                            class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">
                            Export CSV
                        </a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endsection
