<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    protected $fillable = [
        'monitoring_snapshot_id',
        'process_id',
        'alert_type',
        'severity',
        'message',
        'details',
        'acknowledged',
    ];

    protected $casts = [
        'details' => 'json',
        'acknowledged' => 'boolean',
        'created_at' => 'datetime',
    ];

    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(MonitoringSnapshot::class, 'monitoring_snapshot_id');
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }
}
