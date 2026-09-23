<?php

namespace App\Http\Controllers;

use App\Models\SystemAuditLog;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $envPath = base_path('.env');
        $envContent = file_exists($envPath) ? file_get_contents($envPath) : '';

        $config = [
            'db_host'      => env('DB_HOST', '127.0.0.1'),
            'db_port'      => env('DB_PORT', '3306'),
            'db_name'      => env('DB_DATABASE', '—'),
            'db_username'  => env('DB_USERNAME', '—'),
            'vt_key_set'   => ! empty(env('VT_API_KEY', '')),
            'app_env'      => env('APP_ENV', 'local'),
            'app_debug'    => env('APP_DEBUG', false),
            'app_url'      => env('APP_URL', '—'),
            'alert_email'  => env('ALERT_EMAIL', ''),
            'scan_interval'=> env('SCAN_INTERVAL_MINUTES', 5),
            'mail_mailer'  => env('MAIL_MAILER', 'log'),
            'mail_host'    => env('MAIL_HOST', '127.0.0.1'),
            'mail_port'    => env('MAIL_PORT', 2525),
        ];

        // DB connection test
        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();
            $config['db_status'] = 'connected';
        } catch (\Exception $e) {
            $config['db_status'] = 'error: ' . $e->getMessage();
        }

        // Python agent script check
        $agentScript = base_path('../monitoring_agent/live_monitor.py');
        $config['agent_found'] = file_exists($agentScript);
        $config['agent_path']  = realpath($agentScript) ?: $agentScript;

        return view('settings.index', compact('config'));
    }

    public function updateVtKey(Request $request)
    {
        $request->validate([
            // A VirusTotal key is 64 hex characters; allow empty to clear it.
            'vt_api_key' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9]*$/'],
        ], [
            'vt_api_key.regex' => 'The API key may only contain letters and numbers.',
        ]);

        $key = trim($request->input('vt_api_key', ''));
        $this->setEnvValue('VT_API_KEY', $key);

        SystemAuditLog::record('update_vt_key', 'Settings', null, 'VT_API_KEY');

        return back()->with('success', $key ? 'VirusTotal API key updated.' : 'VirusTotal API key cleared.');
    }

    public function updateAlertEmail(Request $request)
    {
        $request->validate([
            'alert_email' => ['nullable', 'email', 'max:255'],
        ]);

        $email = trim($request->input('alert_email', ''));
        $this->setEnvValue('ALERT_EMAIL', $email);

        SystemAuditLog::record('update_alert_email', 'Settings', null, 'ALERT_EMAIL');

        return back()->with('success', $email ? "Alert emails will be sent to {$email}." : 'Alert email cleared — emails will go to all Admin accounts.');
    }

    private function setEnvValue(string $key, string $value): void
    {
        $envPath    = base_path('.env');
        $envContent = file_exists($envPath) ? file_get_contents($envPath) : '';
        $escaped    = preg_quote($key, '/');

        // Strip CR/LF so a value can never inject additional env directives.
        $value = str_replace(["\r", "\n"], '', $value);
        $line  = $key . '=' . $value;

        if (preg_match("/^{$escaped}=.*$/m", $envContent)) {
            // Use a callback so the value is inserted literally; this avoids
            // preg_replace treating $ or \ in the value as backreferences.
            $envContent = preg_replace_callback(
                "/^{$escaped}=.*$/m",
                fn () => $line,
                $envContent,
                1
            );
        } else {
            $envContent .= PHP_EOL . $line;
        }

        file_put_contents($envPath, $envContent);

        // Clear config cache so the new value is picked up
        try { \Illuminate\Support\Facades\Artisan::call('config:clear'); } catch (\Exception) {}
    }
}
