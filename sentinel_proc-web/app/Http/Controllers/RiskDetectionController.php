<?php

namespace App\Http\Controllers;

use App\Models\Process;
use App\Models\ActivityLog;

class RiskDetectionController extends Controller
{
    public function index()
    {
        $query = ActivityLog::whereIn('event_type', ['risk_change', 'vt_flagged', 'first_seen'])
            ->orderByDesc('created_at');

        if ($search = request('search')) {
            $query->where('process_name', 'like', "%{$search}%");
        }
        if ($risk = request('risk')) {
            $query->where('risk_level', $risk);
        }
        if ($event = request('event')) {
            $query->where('event_type', $event);
        }

        $logs = $query->paginate(50)->withQueryString();

        // Summary counts
        $summary = [
            'total'    => ActivityLog::whereIn('event_type', ['risk_change', 'vt_flagged', 'first_seen'])->count(),
            'high'     => ActivityLog::where('risk_level', 'high')->count(),
            'medium'   => ActivityLog::where('risk_level', 'medium')->count(),
            'vt'       => ActivityLog::where('event_type', 'vt_flagged')->count(),
        ];

        return view('risk-detections.index', compact('logs', 'summary'));
    }
}
