<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\DB;

class ActivityLogController extends Controller
{
    public function index()
    {
        $query = ActivityLog::orderByDesc('created_at');

        if ($search = request('search')) {
            $query->where('process_name', 'like', "%{$search}%");
        }
        if ($event = request('event')) {
            $query->where('event_type', $event);
        }
        if ($risk = request('risk')) {
            $query->where('risk_level', $risk);
        }

        $logs   = $query->paginate(50)->withQueryString();
        $events = ActivityLog::select('event_type')->distinct()->pluck('event_type')->sort()->values();

        return view('activity.index', compact('logs', 'events'));
    }
}
