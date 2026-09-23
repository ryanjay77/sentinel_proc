<?php

namespace App\Http\Controllers;

use App\Models\SystemAuditLog;

class SystemAuditLogController extends Controller
{
    public function index()
    {
        $query = SystemAuditLog::orderByDesc('created_at');

        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhere('target_label', 'like', "%{$search}%");
            });
        }
        if ($action = request('action')) {
            $query->where('action', $action);
        }

        $logs    = $query->paginate(50)->withQueryString();
        $actions = SystemAuditLog::select('action')->distinct()->pluck('action')->sort()->values();

        return view('activity.system', compact('logs', 'actions'));
    }
}
