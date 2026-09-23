<?php

namespace App\Services;

use App\Mail\HighRiskAlertMail;
use App\Models\Alert;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AlertNotificationService
{
    /**
     * Check for unacknowledged HIGH risk alerts from the latest scan
     * and send an email to all Admins if any are found.
     *
     * Called automatically by RunMonitoringScan after each scan.
     */
    public function sendIfNeeded(): void
    {
        $recipient = env('ALERT_EMAIL', null);

        // If no explicit address, fall back to all Admin users
        if (! $recipient) {
            $admins = User::where('role', 'admin')->pluck('email')->toArray();
            if (empty($admins)) {
                return;
            }
        }

        // Grab unacknowledged HIGH alerts created in the last 10 minutes
        $alerts = Alert::where('severity', 'high')
            ->where('acknowledged', false)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->get()
            ->map(fn ($a) => [
                'message' => $a->message,
                'details' => $a->details,
            ])
            ->toArray();

        if (empty($alerts)) {
            return;
        }

        $scanTotal = (int) Alert::where('created_at', '>=', now()->subMinutes(10))->count();

        $mail = new HighRiskAlertMail($alerts, $scanTotal);

        try {
            if ($recipient) {
                Mail::to($recipient)->send($mail);
                Log::info('SentinelProc: Alert email sent to ' . $recipient . ' (' . count($alerts) . ' HIGH alerts)');
            } else {
                foreach ($admins as $email) {
                    Mail::to($email)->send($mail);
                }
                Log::info('SentinelProc: Alert email sent to ' . count($admins) . ' admin(s) (' . count($alerts) . ' HIGH alerts)');
            }
        } catch (\Throwable $e) {
            Log::error('SentinelProc: Failed to send alert email — ' . $e->getMessage());
        }
    }
}
