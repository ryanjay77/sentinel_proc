<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMonitoringSnapshotRequest;
use App\Models\Agent;
use App\Models\MonitoringSnapshot;
use App\Models\Process;
use App\Models\Alert;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MonitoringApiController extends Controller
{
    /**
     * Store a new monitoring snapshot from the monitoring agent
     */
    public function storeSnapshot(StoreMonitoringSnapshotRequest $request)
    {
        try {
            $validated = $request->validated();

            // Decode the snapshot JSON
            $snapshotData = json_decode($validated['snapshot'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Invalid JSON in snapshot field',
                    'error' => json_last_error_msg(),
                ], 422);
            }

            // Validate the decoded structure before anything reaches the database.
            // Never trust nested agent payload: enforce hash format, enums and lengths
            // to prevent data poisoning and stored XSS.
            if (! is_array($snapshotData)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Snapshot must decode to an object',
                ], 422);
            }

            $payloadValidator = Validator::make($snapshotData, [
                // The laptop that produced the scan. The envelope value is
                // authoritative; this only matters for older agents that embed
                // the hostname inside the snapshot JSON.
                'hostname'    => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/'],

                'processes'   => ['nullable', 'array'],
                'processes.*.pid'          => ['nullable', 'integer', 'min:0'],
                'processes.*.name'         => ['nullable', 'string', 'max:255'],
                'processes.*.path'         => ['nullable', 'string', 'max:500'],
                'processes.*.cpu_percent'  => ['nullable', 'numeric', 'min:0'],
                'processes.*.memory_mb'    => ['nullable', 'numeric', 'min:0'],
                'processes.*.status'       => ['nullable', 'string', 'max:50'],
                'processes.*.hash'         => ['nullable', 'string', 'regex:/^[a-fA-F0-9]{64}$/'],
                'processes.*.first_seen'   => ['nullable', 'boolean'],
                'processes.*.risk_level'   => ['nullable', 'string', 'in:low,medium,high,critical'],
                'processes.*.score'        => ['nullable', 'integer', 'min:0', 'max:1000'],
                'processes.*.reasons'      => ['nullable', 'array', 'max:20'],
                'processes.*.reasons.*'    => ['nullable', 'string', 'max:100'],
                'processes.*.virus_total_data' => ['nullable', 'array'],

                'alerts'      => ['nullable', 'array'],
                'alerts.*.process_id' => ['nullable', 'integer', 'min:0'],
                'alerts.*.alert_type' => ['nullable', 'string', 'max:100'],
                // Both cases appear in the wild: explicit alerts use lowercase,
                // the dashboard summary the agent embeds uses uppercase.
                'alerts.*.severity'   => ['nullable', 'string', 'in:low,medium,high,critical,LOW,MEDIUM,HIGH,CRITICAL'],
                'alerts.*.message'    => ['nullable', 'string', 'max:1000'],
                'alerts.*.details'    => ['nullable', 'string', 'max:5000'],
            ]);

            if ($payloadValidator->fails()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Snapshot payload failed validation',
                    'errors' => $payloadValidator->errors(),
                ], 422);
            }

            // Agent identity comes from the token itself, never from the payload.
            $agentName = $request->user()?->currentAccessToken()?->name;

            // Which laptop produced the scan. Envelope wins; the snapshot
            // JSON carries the same value for older agents.
            $hostname = $validated['hostname'] ?? $snapshotData['hostname'] ?? null;
            $agentIp = $request->ip();

            // Idempotency: a retried POST carrying the same scan_uuid (e.g.
            // after a network drop) must not store the scan twice.
            if (!empty($validated['scan_uuid'])) {
                $existing = MonitoringSnapshot::where('scan_uuid', $validated['scan_uuid'])->first();
                if ($existing) {
                    if ($hostname) {
                        Agent::updateOrCreate(
                            ['hostname' => $hostname],
                            ['ip_address' => $agentIp, 'last_seen' => now(), 'status' => 'online']
                        );
                    }

                    return response()->json([
                        'ok' => true,
                        'message' => 'Snapshot already ingested (duplicate scan_uuid)',
                        'snapshot_id' => $existing->id,
                        'duplicate' => true,
                    ], 200);
                }
            }

            // The snapshot and its child rows are one logical unit: a failure
            // part-way through (e.g. an over-long value rejected by the column)
            // must not leave a snapshot with missing processes or alerts.
            $snapshot = DB::transaction(function () use ($validated, $snapshotData, $hostname, $agentIp) {
                $snapshot = MonitoringSnapshot::create([
                    'snapshot' => $validated['snapshot'],
                    'scan_uuid' => $validated['scan_uuid'] ?? null,
                    'snapshot_timestamp' => $validated['snapshot_timestamp'] ?? now(),
                    'hostname' => $hostname,
                    'process_count' => $validated['process_count'] ?? null,
                    'cpu_usage' => $validated['cpu_usage'] ?? null,
                    'memory_usage' => $validated['memory_usage'] ?? null,
                    'disk_usage' => $validated['disk_usage'] ?? null,
                    'status' => $validated['status'] ?? 'normal',
                    'processes' => $snapshotData['processes'] ?? null,
                    'alerts' => $snapshotData['alerts'] ?? null,
                    'risk_score' => $snapshotData['risk_score'] ?? null,
                ]);

                $seenRows = [];
                $vtRows = [];
                $createdProcesses = [];

                // API-mode agents send the authoritative scored process list
                // in the envelope; the snapshot JSON carries only the
                // dashboard-shaped summary. Legacy posts embed processes in
                // the snapshot JSON itself, so fall back to that.
                $processEntries = $validated['processes'] ?? null;
                $apiMode = is_array($processEntries) && !empty($processEntries);
                if (! $apiMode) {
                    $processEntries = $snapshotData['processes'] ?? null;
                }

                // Process individual process data if provided
                if (!empty($processEntries) && is_array($processEntries)) {
                    foreach ($processEntries as $processData) {
                        $process = Process::create([
                            'monitoring_snapshot_id' => $snapshot->id,
                            'pid' => $processData['pid'] ?? null,
                            'name' => $processData['name'] ?? 'Unknown',
                            'hostname' => $hostname,
                            'path' => $processData['path'] ?? null,
                            'cpu_percent' => $processData['cpu_percent'] ?? null,
                            'memory_mb' => $processData['memory_mb'] ?? null,
                            'status' => $processData['status'] ?? null,
                            'hash' => $processData['hash'] ?? null,
                            'first_seen' => !empty($processData['first_seen']),
                            'risk_level' => $processData['risk_level'] ?? 'low',
                            'virus_total_data' => $processData['virus_total_data'] ?? null,
                        ]);
                        $createdProcesses[] = ['model' => $process, 'data' => $processData];

                        // Maintain the first-seen registry and the VirusTotal
                        // cache, exactly as the local MySQL agent path does.
                        $hash = $processData['hash'] ?? null;
                        if (!empty($hash)) {
                            $seenRows[$hash] = [
                                'file_hash' => $hash,
                                'process_name' => $processData['name'] ?? null,
                                'file_path' => $processData['path'] ?? null,
                            ];

                            $vt = $processData['virus_total_data'] ?? null;
                            if (is_array($vt) && isset($vt['malicious'], $vt['total'])) {
                                $vtRows[$hash] = [
                                    'file_hash' => $hash,
                                    'vt_checked_at' => now(),
                                    'vt_malicious_count' => (int) $vt['malicious'],
                                    'vt_total_engines' => (int) $vt['total'],
                                ];
                            }
                        }

                        $this->logProcessActivity($snapshot->id, $processData);
                    }
                }

                if ($seenRows) {
                    DB::table('processes_seen')->upsert(
                        array_values($seenRows), 'file_hash', ['process_name', 'file_path']
                    );
                }
                if ($vtRows) {
                    DB::table('processes_seen')->upsert(
                        array_values($vtRows), 'file_hash',
                        ['vt_checked_at', 'vt_malicious_count', 'vt_total_engines']
                    );
                }

                // Process individual alert data if provided. In API mode the
                // snapshot JSON still carries display-only alerts (including
                // the "no anomalies" placeholder), so only a legacy post's
                // embedded alerts are stored verbatim — API-mode alerts are
                // derived from the scored processes, exactly like the local
                // agent writes them.
                if (! $apiMode && !empty($snapshotData['alerts']) && is_array($snapshotData['alerts'])) {
                    foreach ($snapshotData['alerts'] as $alertData) {
                        Alert::create([
                            'monitoring_snapshot_id' => $snapshot->id,
                            'process_id' => $alertData['process_id'] ?? null,
                            'hostname' => $hostname,
                            'alert_type' => $alertData['alert_type'] ?? 'unknown',
                            'severity' => $alertData['severity'] ?? 'low',
                            'message' => $alertData['message'] ?? '',
                            'details' => $alertData['details'] ?? null,
                            'acknowledged' => false,
                        ]);
                    }
                } else {
                    // The API-mode agent sends scored processes and no explicit
                    // alerts, so derive one alert per medium/high process —
                    // the same rows the local agent writes itself.
                    foreach ($createdProcesses as $entry) {
                        $data = $entry['data'];
                        if (!in_array($data['risk_level'] ?? 'low', ['medium', 'high'], true)) {
                            continue;
                        }
                        $reasons = array_values($data['reasons'] ?? []);
                        Alert::create([
                            'monitoring_snapshot_id' => $snapshot->id,
                            'process_id' => $entry['model']->id,
                            'hostname' => $hostname,
                            'alert_type' => 'risk_score',
                            'severity' => $data['risk_level'],
                            'message' => ($data['name'] ?? 'Unknown') . ' triggered risk scoring rules (' . implode(', ', $reasons) . ')',
                            'details' => [
                                'score' => $data['score'] ?? 0,
                                'reasons' => $reasons,
                                'source' => ($data['path'] ?? null) ?: ($data['name'] ?? 'Unknown'),
                                'pid' => $data['pid'] ?? null,
                            ],
                            'acknowledged' => false,
                        ]);
                    }
                }

                if ($hostname) {
                    Agent::updateOrCreate(
                        ['hostname' => $hostname],
                        ['ip_address' => $agentIp, 'last_seen' => now(), 'status' => 'online']
                    );
                }

                return $snapshot;
            });

            return response()->json([
                'ok' => true,
                'message' => 'Snapshot stored successfully',
                'snapshot_id' => $snapshot->id,
                'scan_uuid' => $snapshot->scan_uuid,
                'agent' => $agentName,
                'duplicate' => false,
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Monitoring snapshot storage failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            return response()->json([
                'ok' => false,
                'message' => 'Failed to store snapshot',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal error',
            ], 500);
        }
    }

    /**
     * One call a remote agent makes before scoring: which hashes are already
     * known (first-seen registry), any cached VirusTotal verdicts, and the
     * current allow/deny lists. This replaces direct database access for
     * agents running on other machines.
     */
    public function fetchContext(Request $request)
    {
        $validated = $request->validate([
            'hashes' => ['required', 'array', 'max:1000'],
            'hashes.*' => ['required', 'string', 'regex:/^[a-fA-F0-9]{64}$/'],
        ]);

        try {
            $hashes = array_values(array_unique($validated['hashes']));

            $knownHashes = DB::table('processes_seen')
                ->whereIn('file_hash', $hashes)
                ->pluck('file_hash')
                ->all();

            $vtCache = DB::table('processes_seen')
                ->whereIn('file_hash', $hashes)
                ->whereNotNull('vt_checked_at')
                ->get(['file_hash', 'vt_malicious_count', 'vt_total_engines'])
                ->mapWithKeys(fn ($row) => [$row->file_hash => [
                    'malicious' => (int) $row->vt_malicious_count,
                    'total' => (int) $row->vt_total_engines,
                ]])
                ->all();

            $lists = ['whitelist' => [], 'blacklist' => []];
            foreach (DB::table('process_lists')->get(['type', 'match_by', 'value']) as $rule) {
                if (array_key_exists($rule->type, $lists)) {
                    // Lowercased so the agent's case-insensitive matching
                    // behaves regardless of how a rule was typed in the UI.
                    $lists[$rule->type][] = [
                        'match_by' => $rule->match_by,
                        'value' => strtolower($rule->value),
                    ];
                }
            }

            return response()->json([
                'ok' => true,
                'known_hashes' => $knownHashes,
                'vt_cache' => $vtCache,
                'process_lists' => $lists,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to build monitoring context: ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'message' => 'Failed to fetch monitoring context',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal error',
            ], 500);
        }
    }

    /**
     * Write one activity_logs row per ingested process, classifying the event
     * the same way the local agent does when it writes MySQL directly.
     */
    private function logProcessActivity(int $snapshotId, array $processData): void
    {
        $vt = $processData['virus_total_data'] ?? null;
        if (is_array($vt) && (int) ($vt['malicious'] ?? 0) > 0) {
            $eventType = 'vt_flagged';
        } elseif (!empty($processData['first_seen'])) {
            $eventType = 'first_seen';
        } elseif (in_array($processData['risk_level'] ?? 'low', ['high', 'medium'], true)) {
            $eventType = 'risk_change';
        } else {
            $eventType = 'detected';
        }

        ActivityLog::create([
            'monitoring_snapshot_id' => $snapshotId,
            'process_name' => $processData['name'] ?? 'Unknown',
            'pid' => $processData['pid'] ?? null,
            'event_type' => $eventType,
            'risk_level' => $processData['risk_level'] ?? 'low',
            'risk_score' => $processData['score'] ?? 0,
            'path' => $processData['path'] ?? null,
            'hash' => $processData['hash'] ?? null,
            'details' => [
                'reasons' => array_values($processData['reasons'] ?? []),
                'score' => $processData['score'] ?? 0,
                'user' => $processData['status'] ?? 'unknown',
            ],
        ]);
    }

    /**
     * Get the latest snapshot
     */
    public function getLatestSnapshot()
    {
        try {
            $snapshot = MonitoringSnapshot::latest()->first();

            if (!$snapshot) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No snapshot found',
                ], 404);
            }

            return response()->json([
                'ok' => true,
                'data' => $snapshot,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to retrieve latest snapshot: ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'message' => 'Failed to retrieve snapshot',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal error',
            ], 500);
        }
    }

    /**
     * Get snapshots with pagination
     */
    public function getSnapshots(Request $request)
    {
        try {
            $limit = min((int)$request->query('limit', 50), 500); // Cap at 500
            $snapshots = MonitoringSnapshot::latest()
                ->limit($limit)
                ->get();

            return response()->json([
                'ok' => true,
                'count' => count($snapshots),
                'data' => $snapshots,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to retrieve snapshots: ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'message' => 'Failed to retrieve snapshots',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal error',
            ], 500);
        }
    }

    /**
     * Get a specific snapshot with its processes and alerts
     */
    public function getSnapshot($id)
    {
        try {
            if (!is_numeric($id)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Invalid snapshot ID',
                ], 400);
            }

            $snapshot = MonitoringSnapshot::with(['processes', 'alerts'])->findOrFail($id);

            return response()->json([
                'ok' => true,
                'data' => $snapshot,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Snapshot not found',
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Failed to retrieve snapshot: ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'message' => 'Failed to retrieve snapshot',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal error',
            ], 500);
        }
    }
}


