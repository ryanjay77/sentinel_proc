<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SetupScheduler extends Command
{
    protected $signature   = 'sentinel:schedule-setup';
    protected $description = 'Register the Laravel scheduler with Windows Task Scheduler so scans run automatically';

    public function handle(): int
    {
        $phpPath    = PHP_BINARY;
        $artisan    = base_path('artisan');
        $interval   = (int) env('SCAN_INTERVAL_MINUTES', 5);
        $taskName   = 'SentinelProcScheduler';
        $logPath    = storage_path('logs/task-scheduler.log');

        $this->info('Setting up Windows Task Scheduler...');
        $this->line("PHP binary : {$phpPath}");
        $this->line("Artisan    : {$artisan}");
        $this->line("Interval   : every {$interval} minute(s)");

        // Build the schtasks command
        // Runs: php artisan schedule:run >> log every minute
        // Laravel's own scheduler then decides what actually fires based on the interval
        $cmd = sprintf(
            'schtasks /Create /F /TN "%s" /TR "\"%s\" \"%s\" schedule:run >> \"%s\" 2>&1" /SC MINUTE /MO 1 /RL HIGHEST',
            $taskName,
            $phpPath,
            $artisan,
            $logPath
        );

        exec($cmd, $output, $exitCode);

        if ($exitCode === 0) {
            $this->info("✓ Task '{$taskName}' registered successfully.");
            $this->info("  The scheduler will run every minute and trigger scans every {$interval} minute(s).");
            $this->info("  Logs: {$logPath}");
            $this->newLine();
            $this->warn('To remove the task later, run:');
            $this->line("  schtasks /Delete /TN \"{$taskName}\" /F");
        } else {
            $this->error('Failed to register task. Try running this command as Administrator.');
            foreach ($output as $line) {
                $this->line($line);
            }
            $this->newLine();
            $this->warn('Manual alternative — run this in an elevated terminal:');
            $this->line($cmd);
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
