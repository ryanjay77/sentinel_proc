<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReportService
{
    public function __construct(private LiveSnapshotService $snapshots) {}

    public function catalog(): array
    {
        $snapshot = $this->snapshots->load();
        $generatedAt = isset($snapshot['generated_at'])
            ? Carbon::parse($snapshot['generated_at'])
            : now();

        return [
            [
                'type' => 'daily-audit',
                'slug' => 'daily-audit',
                'title' => 'Daily Process Audit',
                'range' => $generatedAt->toDateString(),
                'category' => 'System health',
                'summary' => $this->auditSummary($snapshot),
            ],
            [
                'type' => 'risk-summary',
                'slug' => 'risk-summary',
                'title' => 'Risk Summary',
                'range' => 'Last 7 days',
                'category' => 'Threat analytics',
                'summary' => $this->riskSummary($snapshot),
            ],
            [
                'type' => 'threat-intel',
                'slug' => 'threat-intel',
                'title' => 'Threat Intelligence',
                'range' => $generatedAt->format('M d, Y'),
                'category' => 'Detection intel',
                'summary' => $this->threatSummary(),
            ],
        ];
    }

    public function generate(string $type): array
    {
        return match ($type) {
            'daily-audit' => $this->dailyAuditReport(),
            'risk-summary' => $this->riskSummaryReport(),
            'threat-intel' => $this->threatIntelReport(),
            default => abort(404),
        };
    }

    private function dailyAuditReport(): array
    {
        $snapshot = $this->snapshots->load() ?? [];
        $processes = $snapshot['processes'] ?? [];
        $stats = collect($snapshot['stats'] ?? []);

        $riskBreakdown = [
            'High Risk' => collect($processes)->where('status', 'High Risk')->count(),
            'Medium Risk' => collect($processes)->where('status', 'Medium Risk')->count(),
            'Normal' => collect($processes)->where('status', 'Normal')->count(),
        ];

        return [
            'title' => 'Daily Process Audit',
            'type' => 'daily-audit',
            'generated_at' => $snapshot['generated_at'] ?? now()->toIso8601String(),
            'overview' => [
                'total_processes' => $stats->firstWhere('label', 'Running Processes')['value'] ?? count($processes),
                'high_risk' => $riskBreakdown['High Risk'],
                'medium_risk' => $riskBreakdown['Medium Risk'],
                'normal' => $riskBreakdown['Normal'],
            ],
            'processes' => $processes,
            'sections' => [
                [
                    'heading' => 'Risk Distribution',
                    'items' => collect($riskBreakdown)->map(fn ($count, $label) => [
                        'label' => $label,
                        'value' => (string) $count,
                    ])->values()->all(),
                ],
            ],
        ];
    }

    private function riskSummaryReport(): array
    {
        $snapshot = $this->snapshots->load() ?? [];
        $alerts = $snapshot['alerts'] ?? [];
        $stats = collect($snapshot['stats'] ?? []);

        $severityCounts = [
            'HIGH' => collect($alerts)->where('severity', 'HIGH')->count(),
            'MEDIUM' => collect($alerts)->where('severity', 'MEDIUM')->count(),
            'LOW' => collect($alerts)->where('severity', 'LOW')->count(),
        ];

        return [
            'title' => 'Risk Summary',
            'type' => 'risk-summary',
            'generated_at' => $snapshot['generated_at'] ?? now()->toIso8601String(),
            'overview' => [
                'high_risk_processes' => $stats->firstWhere('label', 'High Risk')['value'] ?? '0',
                'medium_risk_processes' => $stats->firstWhere('label', 'Medium Risk')['value'] ?? '0',
                'total_alerts' => count($alerts),
            ],
            'alerts' => $alerts,
            'sections' => [
                [
                    'heading' => 'Alerts by Severity',
                    'items' => collect($severityCounts)->map(fn ($count, $label) => [
                        'label' => $label,
                        'value' => (string) $count,
                    ])->values()->all(),
                ],
            ],
        ];
    }

    private function threatIntelReport(): array
    {
        $snapshot = $this->snapshots->load() ?? [];
        $seen = $this->loadProcessesSeen();

        return [
            'title' => 'Threat Intelligence',
            'type' => 'threat-intel',
            'generated_at' => $snapshot['generated_at'] ?? now()->toIso8601String(),
            'overview' => [
                'tracked_hashes' => count($seen),
                'flagged_alerts' => collect($snapshot['alerts'] ?? [])
                    ->whereIn('severity', ['HIGH', 'MEDIUM'])
                    ->count(),
                'vt_checked' => collect($seen)->whereNotNull('vt_checked_at')->count(),
            ],
            'seen_processes' => $seen,
            'alerts' => collect($snapshot['alerts'] ?? [])
                ->whereIn('severity', ['HIGH', 'MEDIUM'])
                ->values()
                ->all(),
            'sections' => [
                [
                    'heading' => 'First-Seen Tracking',
                    'items' => collect($seen)->take(10)->map(fn ($row) => [
                        'label' => $row['process_name'] ?? 'Unknown',
                        'value' => $row['file_hash'] ? substr($row['file_hash'], 0, 12).'…' : 'N/A',
                    ])->all(),
                ],
            ],
        ];
    }

    private function auditSummary(?array $snapshot): string
    {
        $total = collect($snapshot['stats'] ?? [])->firstWhere('label', 'Running Processes')['value'] ?? '0';
        $high = collect($snapshot['stats'] ?? [])->firstWhere('label', 'High Risk')['value'] ?? '0';

        return "{$total} processes scanned, {$high} high-risk flagged.";
    }

    private function riskSummary(?array $snapshot): string
    {
        $alerts = count($snapshot['alerts'] ?? []);

        return "{$alerts} active alerts from the latest scan.";
    }

    private function threatSummary(): string
    {
        $count = count($this->loadProcessesSeen());

        return "{$count} unique executable hashes tracked.";
    }

    private function loadProcessesSeen(): array
    {
        try {
            return DB::table('processes_seen')
                ->orderByDesc('created_at')
                ->limit(50)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        } catch (Throwable) {
            return [];
        }
    }
}
