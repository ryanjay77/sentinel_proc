<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(ReportService $reports)
    {
        return view('reports.index', [
            'reports' => $reports->catalog(),
        ]);
    }

    public function show(string $type, ReportService $reports)
    {
        $report = $reports->generate($type);

        return view('reports.show', compact('report'));
    }

    public function export(string $type, ReportService $reports): StreamedResponse
    {
        $report = $reports->generate($type);
        $filename = "sentinelproc-{$type}-".now()->format('Y-m-d').'.csv';

        \App\Models\SystemAuditLog::record('export_report', 'Report', null, $filename);

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Report', $report['title']]);
            fputcsv($handle, ['Generated', $report['generated_at']]);
            fputcsv($handle, []);

            fputcsv($handle, ['Overview']);
            foreach ($report['overview'] as $label => $value) {
                fputcsv($handle, [ucwords(str_replace('_', ' ', $label)), $value]);
            }

            if (! empty($report['sections'])) {
                fputcsv($handle, []);
                foreach ($report['sections'] as $section) {
                    fputcsv($handle, [$section['heading']]);
                    fputcsv($handle, ['Label', 'Value']);
                    foreach ($section['items'] as $item) {
                        fputcsv($handle, [$item['label'], $item['value']]);
                    }
                }
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
