<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class DashboardAnalyticsService
{
    private const RISK_LEVELS = ['high', 'medium', 'low'];

    private const SEVERITIES = ['critical', 'high', 'medium', 'low'];

    private const DISCOVERY_DAYS = 14;

    private const TOP_CONSUMERS = 5;

    public function summary(): array
    {
        try {
            return [
                'riskLevels' => $this->countsBy('processes', 'risk_level', self::RISK_LEVELS),
                'severities' => $this->countsBy('alerts', 'severity', self::SEVERITIES),
                'discoveries' => $this->discoveriesByDay(),
                'consumers' => $this->topMemoryConsumers(),
            ];
        } catch (Throwable) {
            return [
                'riskLevels' => ['labels' => [], 'values' => []],
                'severities' => ['labels' => [], 'values' => []],
                'discoveries' => ['labels' => [], 'values' => []],
                'consumers' => ['labels' => [], 'values' => []],
            ];
        }
    }

    private function countsBy(string $table, string $column, array $order): array
    {
        $counts = DB::table($table)
            ->selectRaw("{$column} as bucket, COUNT(*) as total")
            ->groupBy($column)
            ->pluck('total', 'bucket');

        $values = collect($order)
            ->map(fn ($key) => (int) ($counts[$key] ?? 0))
            ->all();

        return [
            'labels' => array_map(fn ($key) => ucfirst($key), $order),
            'values' => array_values($values),
        ];
    }

    private function discoveriesByDay(): array
    {
        $rows = DB::table('processes_seen')
            ->whereNotNull('created_at')
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at) desc')
            ->limit(self::DISCOVERY_DAYS)
            ->get()
            ->reverse()
            ->values();

        return [
            'labels' => $rows->map(fn ($row) => Carbon::parse($row->day)->format('M j'))->all(),
            'values' => $rows->map(fn ($row) => (int) $row->total)->all(),
        ];
    }

    private function topMemoryConsumers(): array
    {
        $rows = DB::table('processes')
            ->orderByDesc('memory_mb')
            ->limit(self::TOP_CONSUMERS)
            ->get(['name', 'memory_mb']);

        return [
            'labels' => $rows->map(fn ($row) => $row->name)->all(),
            'values' => $rows->map(fn ($row) => round((float) $row->memory_mb, 1))->all(),
        ];
    }
}
