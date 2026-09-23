<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Services\LiveSnapshotService;

class AlertController extends Controller
{
    private const DEFAULT_ALERTS = [
        ['severity' => 'HIGH', 'title' => 'Suspicious executable from Temp folder', 'time' => '2 mins ago', 'source' => 'C:\\Users\\admin\\AppData\\Local\\Temp\\update.exe'],
        ['severity' => 'MEDIUM', 'title' => 'Unexpected privilege escalation attempt', 'time' => '9 mins ago', 'source' => 'powershell.exe'],
        ['severity' => 'LOW', 'title' => 'First-seen hash detected', 'time' => '18 mins ago', 'source' => 'new_installer.exe'],
    ];

    public function index(LiveSnapshotService $snapshots)
    {
        $query = Alert::with(['snapshot', 'process'])->orderByDesc('created_at');

        if ($search = request('search')) {
            $query->where('message', 'like', "%{$search}%");
        }

        if ($severity = request('severity')) {
            $query->where('severity', strtolower($severity));
        }

        if ($status = request('status')) {
            $query->where('acknowledged', $status === 'acknowledged');
        }

        $alerts = $query->paginate(50)->withQueryString();

        if ($alerts->isNotEmpty()) {
            return view('alerts', ['alerts' => $alerts]);
        }

        $snapshot = $snapshots->load();

        return view('alerts', [
            'alerts' => $snapshot['alerts'] ?? self::DEFAULT_ALERTS,
        ]);
    }

    public function show($id)
    {
        $alert = Alert::with(['snapshot', 'process'])->findOrFail($id);

        return view('alerts.show', ['alert' => $alert]);
    }

    public function acknowledge($id)
    {
        $alert = Alert::findOrFail($id);
        $alert->update(['acknowledged' => true]);

        \App\Models\SystemAuditLog::record('acknowledge_alert', 'Alert', $id, $alert->message);

        return response()->json(['ok' => true, 'message' => 'Alert acknowledged']);
    }

    public function destroy($id)
    {
        $alert = Alert::findOrFail($id);
        $label = $alert->message;
        $alert->delete();

        \App\Models\SystemAuditLog::record('delete_alert', 'Alert', $id, $label);

        return redirect()->route('alerts.index')
            ->with('success', 'Alert deleted successfully.');
    }
}
