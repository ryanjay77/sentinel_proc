<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

class LiveSnapshotService
{
    /**
     * Number of process records displayed in the dashboard.
     *
     * The agent can send hundreds of processes, but displaying
     * everything at once is unnecessary for the dashboard.
     */
    private const PROCESS_DISPLAY_LIMIT = 50;

    /**
     * Legacy local snapshot path.
     */
    public function snapshotPath(): string
    {
        return base_path('../monitoring_agent/live_snapshot.json');
    }

    /**
     * Load the latest monitoring snapshot.
     *
     * IMPORTANT:
     * The API stores the complete process list in the `processes`
     * table. Therefore, the dashboard should read process records
     * belonging to the latest snapshot instead of using the
     * dashboard-limited `snapshot.processes` array.
     */
    public function load(?string $hostname = null): ?array
    {
        try {
            $snapshotQuery = DB::table('monitoring_snapshots')
                ->select([
                    'id',
                    'snapshot',
                    'snapshot_timestamp',
                    'process_count',
                    'status',
                    'hostname',
                ])
                ->whereNotNull('snapshot')
                ->when(
                    $hostname,
                    fn ($query) => $query->where('hostname', $hostname)
                )
                ->orderByDesc('id');

            $snapshotRow = $snapshotQuery->first();

            if ($snapshotRow) {
                $snapshot = $this->decodeSnapshot($snapshotRow->snapshot);

                if (is_array($snapshot)) {
                    /*
                     * Always use the database process records.
                     *
                     * The agent's JSON snapshot intentionally contains
                     * only the top 8 dashboard processes.
                     */
                    $processQuery = DB::table('processes')
                        ->where('monitoring_snapshot_id', $snapshotRow->id);

                    if ($hostname !== null) {
                        $processQuery->where('hostname', $hostname);
                    }

                    $processes = $processQuery
                        ->orderByRaw("
                            CASE risk_level
                                WHEN 'high' THEN 0
                                WHEN 'medium' THEN 1
                                WHEN 'low' THEN 2
                                ELSE 3
                            END
                        ")
                        ->orderByDesc('cpu_percent')
                        ->limit(self::PROCESS_DISPLAY_LIMIT)
                        ->get([
                            'pid',
                            'name',
                            'status',
                            'cpu_percent',
                            'memory_mb',
                            'risk_level',
                            'hostname',
                        ])
                        ->map(function ($process) {
                            /*
                             * At the moment, the agent stores the Windows
                             * username in the existing `status` database
                             * column. Keep that value available as `user`
                             * for backward compatibility.
                             */
                            $user = $process->status ?: 'unknown';

                            $riskStatus = match (strtolower((string) $process->risk_level)) {
                                'high' => 'High Risk',
                                'medium' => 'Medium Risk',
                                default => 'Normal',
                            };

                            return [
                                'pid' => (int) $process->pid,
                                'name' => $process->name ?: 'Unknown',
                                'user' => $user,
                                'cpu' => number_format(
                                    (float) $process->cpu_percent,
                                    1
                                ) . '%',
                                'memory' => number_format(
                                    (float) $process->memory_mb,
                                    0
                                ) . ' MB',
                                'status' => $riskStatus,
                            ];
                        })
                        ->values()
                        ->all();

                    /*
                     * Replace the capped snapshot process list with
                     * the complete/latest database process list.
                     */
                    $snapshot['processes'] = $processes;

                    /*
                     * Keep useful metadata from the database.
                     */
                    if (
                        !isset($snapshot['hostname']) ||
                        $snapshot['hostname'] === ''
                    ) {
                        $snapshot['hostname'] = $snapshotRow->hostname;
                    }

                    if (
                        !isset($snapshot['generated_at']) ||
                        empty($snapshot['generated_at'])
                    ) {
                        $snapshot['generated_at'] =
                            $snapshotRow->snapshot_timestamp;
                    }

                    /*
                     * Make sure stats exist even if an older snapshot
                     * did not contain them.
                     */
                    if (empty($snapshot['stats'])) {
                        $snapshot['stats'] = [
                            [
                                'label' => 'Running Processes',
                                'value' => (string) ($snapshotRow->process_count ?? count($processes)),
                                'trend' => '',
                                'tone' => 'primary',
                            ],
                        ];
                    }

                    /*
                     * Keep alerts from the snapshot when available.
                     */
                    if (!isset($snapshot['alerts'])) {
                        $snapshot['alerts'] = [];
                    }

                    return $snapshot;
                }
            }
        } catch (Throwable $exception) {
            /*
             * Do not allow a dashboard read failure to crash
             * the monitoring page.
             */
        }

        /*
         * Legacy local fallback.
         *
         * This is retained for local development only.
         */
        if ($hostname !== null) {
            return null;
        }

        return $this->loadLocalSnapshot();
    }

    /**
     * Return all hostnames that have reported monitoring snapshots.
     */
    public function hostnames(): array
    {
        try {
            return DB::table('monitoring_snapshots')
                ->whereNotNull('hostname')
                ->where('hostname', '!=', '')
                ->distinct()
                ->orderBy('hostname')
                ->pluck('hostname')
                ->all();
        } catch (Throwable $exception) {
            return [];
        }
    }

    /**
     * Decode a snapshot safely.
     *
     * The API currently receives the snapshot as a JSON string.
     * This method supports both:
     *
     * 1. Normal JSON object:
     *    {"stats": [...], "processes": [...]}
     *
     * 2. Double-encoded JSON string:
     *    "{\"stats\":[...]}"
     *
     * This makes the dashboard compatible with existing records.
     */
    private function decodeSnapshot(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        /*
         * Handle a JSON string containing JSON.
         */
        if (is_string($decoded)) {
            $decodedAgain = json_decode($decoded, true);

            if (is_array($decodedAgain)) {
                return $decodedAgain;
            }
        }

        return null;
    }

    /**
     * Load the legacy local JSON snapshot.
     */
    private function loadLocalSnapshot(): ?array
    {
        try {
            $path = $this->snapshotPath();

            if (!is_file($path)) {
                return null;
            }

            $contents = file_get_contents($path);

            if ($contents === false || trim($contents) === '') {
                return null;
            }

            $snapshot = json_decode($contents, true);

            if (!is_array($snapshot)) {
                return null;
            }

            return $snapshot;
        } catch (Throwable $exception) {
            return null;
        }
    }
}