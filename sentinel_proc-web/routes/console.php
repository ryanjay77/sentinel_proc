<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| SentinelProc — Scheduled Monitoring Scan
|--------------------------------------------------------------------------
| Runs the Python monitoring agent on a configurable interval.
| Interval is controlled by SCAN_INTERVAL_MINUTES in .env (default: 5).
|
| To activate on Windows, register the scheduler with Task Scheduler:
|   php artisan sentinel:schedule-setup
|
| Or run manually in a terminal (keeps running):
|   php artisan schedule:work
*/
Schedule::command('sentinel:scan')
    ->cron('*/' . (int) env('SCAN_INTERVAL_MINUTES', 5) . ' * * * *')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/sentinel-scan.log'));
