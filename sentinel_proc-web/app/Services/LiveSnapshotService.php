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

    public function load(): ?array
    {
        try {
            $candidates = DB::table('monitoring_snapshots')
                ->whereNotNull('snapshot')
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

    private function isValidSnapshot(mixed $snapshot): bool
    {
        return is_array($snapshot)
            && ! empty($snapshot['stats'])
            && ! empty($snapshot['processes'])
            && ! empty($snapshot['alerts']);
    }
}
