<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Services\DashboardAnalyticsService;
use App\Services\LiveSnapshotService;
use Illuminate\Http\Request;

class MonitoringController extends Controller
{
    private const DEFAULT_STATS = [
        ['label' => 'Running Processes', 'value' => '184', 'trend' => '+12%', 'tone' => 'primary'],
        ['label' => 'High Risk', 'value' => '7', 'trend' => '+3', 'tone' => 'danger'],
        ['label' => 'Medium Risk', 'value' => '19', 'trend' => '-4%', 'tone' => 'warning'],
        ['label' => 'Whitelisted', 'value' => '96', 'trend' => '+5%', 'tone' => 'success'],
    ];

    private const DEFAULT_PROCESSES = [
        ['pid' => 2048, 'name' => 'chrome.exe', 'user' => 'ryanjay', 'cpu' => '28.4%', 'memory' => '612 MB', 'status' => 'Normal'],
        ['pid' => 4812, 'name' => 'powershell.exe', 'user' => 'ryanjay', 'cpu' => '71.3%', 'memory' => '240 MB', 'status' => 'High Risk'],
        ['pid' => 6171, 'name' => 'cmd.exe', 'user' => 'administrator', 'cpu' => '38.9%', 'memory' => '190 MB', 'status' => 'Medium Risk'],
        ['pid' => 7720, 'name' => 'mshta.exe', 'user' => 'ryanjay', 'cpu' => '82.2%', 'memory' => '410 MB', 'status' => 'High Risk'],
    ];

    private const DEFAULT_ALERTS = [
        ['severity' => 'HIGH', 'title' => 'Suspicious executable from Temp folder', 'time' => '2 mins ago', 'source' => 'C:\\Users\\admin\\AppData\\Local\\Temp\\update.exe'],
        ['severity' => 'MEDIUM', 'title' => 'Unexpected privilege escalation attempt', 'time' => '9 mins ago', 'source' => 'powershell.exe'],
        ['severity' => 'LOW', 'title' => 'First-seen hash detected', 'time' => '18 mins ago', 'source' => 'new_installer.exe'],
    ];

    /**
     * Show the dashboard
     */
    public function index(Request $request, LiveSnapshotService $snapshots, DashboardAnalyticsService $analytics)
    {
        $hostnameFilter = $request->query('hostname');
        if (! is_string($hostnameFilter) || $hostnameFilter === ''
            || ! preg_match('/^[A-Za-z0-9._-]{1,255}$/', $hostnameFilter)) {
            $hostnameFilter = null;
        }

        $snapshot = $snapshots->load($hostnameFilter);
        $agents = Agent::query()->orderBy('hostname')->get()->map(function (Agent $agent) {
            return [
                'hostname' => $agent->hostname,
                'ip_address' => $agent->ip_address,
                'last_seen' => $agent->last_seen,
                'is_online' => $agent->last_seen !== null
                    && $agent->last_seen->greaterThanOrEqualTo(now()->subSeconds(30)),
            ];
        });

        // Placeholder data only fills an empty unfiltered dashboard; a
        // filtered miss means the laptop has no scans yet and stays empty.
        $useDefaults = $snapshot === null && $hostnameFilter === null;

        return view('dashboard', [
            'stats' => $snapshot['stats'] ?? ($useDefaults ? self::DEFAULT_STATS : []),
            'processes' => $snapshot['processes'] ?? ($useDefaults ? self::DEFAULT_PROCESSES : []),
            'alerts' => $snapshot['alerts'] ?? ($useDefaults ? self::DEFAULT_ALERTS : []),
            'generatedAt' => $snapshot['generated_at'] ?? null,
            'analytics' => $analytics->summary(),
            'hostnameFilter' => $hostnameFilter,
            'snapshotHostname' => $hostnameFilter ?: ($snapshot['hostname'] ?? null),
            'hostnames' => $snapshots->hostnames(),
            'agents' => $agents,
        ]);
    }

    /**
     * Refresh monitoring snapshot by running Python script in the background.
     * The script is dispatched asynchronously so the HTTP response returns
     * immediately — the dashboard reloads after a short delay on the frontend.
     */
    public function refresh()
    {
        // Never launch the real agent from the test suite. It writes a full
        // snapshot into the live database and overwrites live_snapshot.json, so
        // running the tests would pollute (and, with a VirusTotal key set, spend
        // quota on) the very data being demonstrated. The authorization decision
        // a test is checking happens before this point, so skipping is enough.
        if (app()->runningUnitTests()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Scan skipped in the test environment.',
            ]);
        }

        $script = base_path('../monitoring_agent/live_monitor.py');

        if (!file_exists($script)) {
            return response()->json(['ok' => false, 'message' => 'Monitoring script missing.'], 500);
        }

        // Find a working Python executable
        $python   = null;
        $pythons  = ['python', 'py', 'python.exe'];
        foreach ($pythons as $candidate) {
            exec('"' . $candidate . '" --version 2>&1', $out, $code);
            if ($code === 0) { $python = $candidate; break; }
        }

        if (!$python) {
            return response()->json(['ok' => false, 'message' => 'Python not found on this machine.'], 500);
        }

        // Launch the scan as a fully detached background process.
        // We write a temporary .bat file and run it via cmd /C start /B
        // so the process is completely detached from the PHP process and
        // the HTTP response returns immediately without waiting.
        $logFile = storage_path('logs/sentinel-scan.log');
        $bat     = storage_path('app/sentinel-scan-runner.bat');

        // Write the bat file
        file_put_contents($bat,
            '@echo off' . "\r\n" .
            '"' . $python . '" "' . $script . '" >> "' . $logFile . '" 2>&1' . "\r\n"
        );

        // Execute bat detached — cmd /C start /B is a shell command that
        // creates a new detached process; the PHP process does not wait.
        $cmd = 'cmd /C start /B "" "' . $bat . '"';
        pclose(popen($cmd, 'r'));
        // Note: popen with cmd /C start /B works because we're calling cmd.exe
        // (a real executable) not just the 'start' built-in directly.

        \App\Models\SystemAuditLog::record('refresh_snapshot');

        return response()->json([
            'ok'      => true,
            'message' => 'Scan started. Dashboard will refresh in a moment.',
        ]);
    }
}

