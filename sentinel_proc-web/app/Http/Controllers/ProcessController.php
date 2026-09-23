<?php

namespace App\Http\Controllers;

use App\Models\Process;
use App\Services\LiveSnapshotService;

class ProcessController extends Controller
{
    private const DEFAULT_PROCESSES = [
        ['pid' => 2048, 'name' => 'chrome.exe', 'user' => 'ryanjay', 'cpu' => '28.4%', 'memory' => '612 MB', 'status' => 'Normal'],
        ['pid' => 4812, 'name' => 'powershell.exe', 'user' => 'ryanjay', 'cpu' => '71.3%', 'memory' => '240 MB', 'status' => 'High Risk'],
        ['pid' => 6171, 'name' => 'cmd.exe', 'user' => 'administrator', 'cpu' => '38.9%', 'memory' => '190 MB', 'status' => 'Medium Risk'],
        ['pid' => 7720, 'name' => 'mshta.exe', 'user' => 'ryanjay', 'cpu' => '82.2%', 'memory' => '410 MB', 'status' => 'High Risk'],
    ];

    public function index(LiveSnapshotService $snapshots)
    {
        $query = Process::with(['snapshot', 'alerts'])->orderByDesc('created_at');

        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('pid', 'like', "%{$search}%")
                  ->orWhere('status', 'like', "%{$search}%");
            });
        }

        if ($risk = request('risk')) {
            $riskMap = ['High Risk' => 'high', 'Medium Risk' => 'medium', 'Normal' => 'low'];
            if (isset($riskMap[$risk])) {
                $query->where('risk_level', $riskMap[$risk]);
            }
        }

        $processes = $query->paginate(50)->withQueryString();

        if ($processes->isNotEmpty()) {
            return view('processes', ['processes' => $processes]);
        }

        $snapshot = $snapshots->load();

        return view('processes', [
            'processes' => $snapshot['processes'] ?? self::DEFAULT_PROCESSES,
        ]);
    }

    public function show($id)
    {
        $process = Process::with(['snapshot', 'alerts'])->findOrFail($id);

        return view('processes.show', ['process' => $process]);
    }

    private function mapRiskLevel(?string $level): string
    {
        return match (strtoupper((string) $level)) {
            'HIGH' => 'High Risk',
            'MEDIUM' => 'Medium Risk',
            default => 'Normal',
        };
    }
}
