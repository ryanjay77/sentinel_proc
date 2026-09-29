<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Throttle login attempts per email+IP to slow credential brute-forcing.
        RateLimiter::for('login', function (Request $request) {
            $key = Str::lower((string) $request->input('email', '')) . '|' . $request->ip();

            return Limit::perMinute(5)->by($key);
        });

        RateLimiter::for('agent-ingest', function (Request $request) {
            $token = $request->bearerToken();
            $tokenKey = $token === null
                ? 'missing|' . $request->ip()
                : hash('sha256', $token);

            return [
                Limit::perMinute((int) config('monitoring_api.rate_limit_per_token', 60))
                    ->by('agent-token|' . $tokenKey),
                Limit::perMinute((int) config('monitoring_api.rate_limit_per_ip', 120))
                    ->by('agent-ip|' . $request->ip()),
            ];
        });
    }
}
