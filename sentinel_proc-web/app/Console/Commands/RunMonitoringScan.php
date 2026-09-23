<?php

namespace App\Console\Commands;

use App\Models\SystemAuditLog;
use App\Services\AlertNotificationService;
use Illuminate\Console\Command;

class RunMonitoringScan extends Command
{
    protected $signature   = 'sentinel:scan';
    protected $description = 'Run the Python monitoring agent and refresh the live dashboard snapshot';

    public function handle(): int
    {
        $script = base_path('../monitoring_agent/live_monitor.py');

        if (! file_exists($script)) {
            $this->error('Monitoring script not found: ' . $script);
            return self::FAILURE;
        }

        $this->info('[' . now()->format('Y-m-d H:i:s') . '] Starting monitoring scan...');

        $pythons  = ['python', 'py', 'python.exe'];
        $output   = [];
        $exitCode = 1;

        foreach ($pythons as $python) {
            exec('"' . $python . '" "' . $script . '" 2>&1', $output, $exitCode);
            if ($exitCode === 0) {
                break;
            }
            $output = [];
        }

        foreach ($output as $line) {
            $this->line($line);
        }

        if ($exitCode !== 0) {
            $this->error('Scan failed with exit code ' . $exitCode);
            return self::FAILURE;
        }

        $this->info('[' . now()->format('Y-m-d H:i:s') . '] Scan completed successfully.');

        // Send email alert if any HIGH risk processes were detected
        app(AlertNotificationService::class)->sendIfNeeded();

        // Record in audit log (system-triggered, no user)
        SystemAuditLog::create([
            'user_id'      => null,
            'user_name'    => 'Scheduler',
            'action'       => 'scheduled_scan',
            'target_type'  => null,
            'target_id'    => null,
            'target_label' => 'Automated monitoring scan',
            'ip_address'   => '127.0.0.1',
            'meta'         => json_encode(['interval' => env('SCAN_INTERVAL_MINUTES', 5)]),
        ]);

        return self::SUCCESS;
    }
}
