<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMonitoringSnapshotRequest;
use App\Models\MonitoringSnapshot;
use App\Models\Process;
use App\Models\Alert;
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
                'processes'   => ['nullable', 'array'],
                'processes.*.pid'          => ['nullable', 'integer', 'min:0'],
                'processes.*.name'         => ['nullable', 'string', 'max:255'],
                'processes.*.path'         => ['nullable', 'string', 'max:1000'],
                'processes.*.cpu_percent'  => ['nullable', 'numeric', 'min:0'],
                'processes.*.memory_mb'    => ['nullable', 'numeric', 'min:0'],
                'processes.*.status'       => ['nullable', 'string', 'max:50'],
                'processes.*.hash'         => ['nullable', 'string', 'regex:/^[a-fA-F0-9]{64}$/'],
                'processes.*.first_seen'   => ['nullable', 'string', 'max:50'],
                'processes.*.risk_level'   => ['nullable', 'string', 'in:low,medium,high,critical'],
                'processes.*.virus_total_data' => ['nullable', 'array'],

                'alerts'      => ['nullable', 'array'],
                'alerts.*.process_id' => ['nullable', 'integer', 'min:0'],
                'alerts.*.alert_type' => ['nullable', 'string', 'max:100'],
                'alerts.*.severity'   => ['nullable', 'string', 'in:low,medium,high,critical'],
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

            // The snapshot and its child rows are one logical unit: a failure
            // part-way through (e.g. an over-long value rejected by the column)
            // must not leave a snapshot with missing processes or alerts.
            $snapshot = DB::transaction(function () use ($validated, $snapshotData) {
                $snapshot = MonitoringSnapshot::create([
                    'snapshot' => $validated['snapshot'],
                    'snapshot_timestamp' => $validated['snapshot_timestamp'] ?? now(),
                    'process_count' => $validated['process_count'] ?? null,
                    'cpu_usage' => $validated['cpu_usage'] ?? null,
                    'memory_usage' => $validated['memory_usage'] ?? null,
                    'disk_usage' => $validated['disk_usage'] ?? null,
                    'status' => $validated['status'] ?? 'normal',
                    'processes' => $snapshotData['processes'] ?? null,
                    'alerts' => $snapshotData['alerts'] ?? null,
                    'risk_score' => $snapshotData['risk_score'] ?? null,
                ]);

                // Process individual process data if provided
                if (!empty($snapshotData['processes']) && is_array($snapshotData['processes'])) {
                    foreach ($snapshotData['processes'] as $processData) {
                        Process::create([
                            'monitoring_snapshot_id' => $snapshot->id,
                            'pid' => $processData['pid'] ?? null,
                            'name' => $processData['name'] ?? 'Unknown',
                            'path' => $processData['path'] ?? null,
                            'cpu_percent' => $processData['cpu_percent'] ?? null,
                            'memory_mb' => $processData['memory_mb'] ?? null,
                            'status' => $processData['status'] ?? null,
                            'hash' => $processData['hash'] ?? null,
                            'first_seen' => $processData['first_seen'] ?? null,
                            'risk_level' => $processData['risk_level'] ?? 'low',
                            'virus_total_data' => $processData['virus_total_data'] ?? null,
                        ]);
                    }
                }

                // Process individual alert data if provided
                if (!empty($snapshotData['alerts']) && is_array($snapshotData['alerts'])) {
                    foreach ($snapshotData['alerts'] as $alertData) {
                        Alert::create([
                            'monitoring_snapshot_id' => $snapshot->id,
                            'process_id' => $alertData['process_id'] ?? null,
                            'alert_type' => $alertData['alert_type'] ?? 'unknown',
                            'severity' => $alertData['severity'] ?? 'low',
                            'message' => $alertData['message'] ?? '',
                            'details' => $alertData['details'] ?? null,
                            'acknowledged' => false,
                        ]);
                    }
                }

                return $snapshot;
            });

            return response()->json([
                'ok' => true,
                'message' => 'Snapshot stored successfully',
                'snapshot_id' => $snapshot->id,
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


