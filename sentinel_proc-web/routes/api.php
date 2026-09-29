<?php

use App\Http\Controllers\Api\MonitoringApiController;
use Illuminate\Support\Facades\Route;

// Public endpoint for authentication/token generation (TODO: implement)
// Route::post('/login', [AuthController::class, 'login'])->name('api.login');

Route::get('/health', fn () => response()->json(['ok' => true]))
    ->name('api.health');

// Apply limits before authentication so invalid tokens and guests are limited too.
Route::middleware('throttle:agent-ingest')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        // Writes are restricted to tokens explicitly granted the monitoring:write
        // ability (issued to the monitoring agent via app:generate-monitoring-token).
        Route::post('/monitoring/snapshot', [MonitoringApiController::class, 'storeSnapshot'])
            ->middleware('ability:monitoring:write')
            ->name('api.monitoring.store');

        // Agents fetch scan context without direct database access.
        Route::post('/monitoring/context', [MonitoringApiController::class, 'fetchContext'])
            ->middleware('ability:monitoring:context')
            ->name('api.monitoring.context');
    });
});

// Protected read-only monitoring endpoints (requires Sanctum token)
Route::middleware('auth:sanctum')->group(function () {
    // Reads mirror the web RBAC: only Admin and Security Analyst may view telemetry.
    Route::middleware('role:admin,analyst')->group(function () {
        Route::get('/monitoring/snapshot/latest', [MonitoringApiController::class, 'getLatestSnapshot'])->name('api.monitoring.latest');
        Route::get('/monitoring/snapshots', [MonitoringApiController::class, 'getSnapshots'])->name('api.monitoring.list');
        Route::get('/monitoring/snapshot/{id}', [MonitoringApiController::class, 'getSnapshot'])->name('api.monitoring.show');
    });
});

// TODO: Create public health check endpoint if needed
// Route::get('/health', fn() => response()->json(['ok' => true]));
