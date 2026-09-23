<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\ProcessController;
use App\Http\Controllers\ProcessListController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RiskDetectionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SystemAuditLogController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VirusTotalController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Viewer, Analyst & Admin can view Dashboard & System Status
    Route::get('/', [MonitoringController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [MonitoringController::class, 'index'])->name('monitoring.index');

    // Security Analyst & Admin access (Monitoring, Investigating, Reports)
    Route::middleware('role:admin,analyst')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{type}/export', [ReportController::class, 'export'])->name('reports.export');
        Route::get('/reports/{type}', [ReportController::class, 'show'])->name('reports.show');

        Route::post('/monitor/refresh', [MonitoringController::class, 'refresh'])->name('monitoring.refresh');

        Route::get('/processes', [ProcessController::class, 'index'])->name('processes.index');
        Route::get('/processes/{id}', [ProcessController::class, 'show'])->name('processes.show');

        Route::get('/activity', [ActivityLogController::class, 'index'])->name('activity.index');
        Route::get('/risk-detections', [RiskDetectionController::class, 'index'])->name('risk-detections.index');
        Route::get('/virus-total', [VirusTotalController::class, 'index'])->name('virus-total.index');

        Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
        Route::get('/alerts/{id}', [AlertController::class, 'show'])->name('alerts.show');
        Route::post('/alerts/{id}/acknowledge', [AlertController::class, 'acknowledge'])->name('alerts.acknowledge');
    });

    // Administrator only access (User Management, Rules, System Logs, Config)
    Route::middleware('role:admin')->group(function () {
        Route::delete('/alerts/{id}', [AlertController::class, 'destroy'])->name('alerts.destroy');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{id}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{id}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{id}', [UserController::class, 'destroy'])->name('users.destroy');

        Route::get('/activity/system', [SystemAuditLogController::class, 'index'])->name('activity.system');

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings/vt-key', [SettingsController::class, 'updateVtKey'])->name('settings.vt-key');
        Route::put('/settings/alert-email', [SettingsController::class, 'updateAlertEmail'])->name('settings.alert-email');

        Route::get('/process-rules', [\App\Http\Controllers\ProcessRuleController::class, 'index'])->name('rules.index');
        Route::post('/process-rules', [\App\Http\Controllers\ProcessRuleController::class, 'store'])->name('rules.store');
        Route::delete('/process-rules/{id}', [\App\Http\Controllers\ProcessRuleController::class, 'destroy'])->name('rules.destroy');

        // Whitelist / Blacklist
        Route::get('/process-lists', [ProcessListController::class, 'index'])->name('process-lists.index');
        Route::post('/process-lists', [ProcessListController::class, 'store'])->name('process-lists.store');
        Route::delete('/process-lists/{id}', [ProcessListController::class, 'destroy'])->name('process-lists.destroy');
    });
});
