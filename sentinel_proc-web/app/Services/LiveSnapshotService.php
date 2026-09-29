<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

class LiveSnapshotService
{
    private const SNAPSHOT_SCAN_LIMIT = 25;

    public function snapshotPath(): string
    {
        return base_path('../monitoring_agent/live_snapshot.json');
    }

    public function load(?string $hostname = null): ?array
    {
        try {
            $candidates = DB::table('monitoring_snapshots')
                ->whereNotNull('snapshot')
                ->when($hostname, fn ($query) => $query->where('hostname', $hostname))
                ->orderByDesc('id')
                ->limit(self::SNAPSHOT_SCAN_LIMIT)
                ->pluck('snapshot');

            foreach ($candidates as $candidate) {
                if (! is_string($candidate) || $candidate === '') {
                    continue;
                }

                $snapshot = json_decode($candidate, true);
                if ($this->isValidSnapshot($snapshot)) {
                    return $snapshot;
                }
            }
        } catch (Throwable) {
            // Fall back to the JSON file.
        }

        // The JSON file is this machine's local fallback — it cannot answer a
        // per-laptop question, so a filtered miss stays a miss.
        if ($hostname !== null) {
            return null;
        }

        $path = $this->snapshotPath();
        if (! is_file($path)) {
            return null;
        }

        $json = @file_get_contents($path);
        if ($json === false) {
            return null;
        }

        $snapshot = json_decode($json, true);

        return $this->isValidSnapshot($snapshot) ? $snapshot : null;
    }

    /**
     * Distinct hostnames the dashboard filter can offer, oldest laptops
     * included — not capped to the recent-scan window.
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
        } catch (Throwable) {
            return [];
        }
    }

    private function isValidSnapshot(mixed $snapshot): bool
    {
        return is_array($snapshot)
            && ! empty($snapshot['stats'])
            && ! empty($snapshot['processes'])
            && ! empty($snapshot['alerts']);
    }
}
