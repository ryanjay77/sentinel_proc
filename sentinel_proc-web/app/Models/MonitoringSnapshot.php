<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonitoringSnapshot extends Model
{
    protected $fillable = [
        'snapshot',
        'scan_uuid',
        'snapshot_timestamp',
        'hostname',
        'process_count',
        'cpu_usage',
        'memory_usage',
        'disk_usage',
        'processes',
        'alerts',
        'risk_score',
        'status',
    ];

    protected $casts = [
        'processes' => 'json',
        'alerts' => 'json',
        'risk_score' => 'json',
        'snapshot' => 'json',
        'snapshot_timestamp' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function processes(): HasMany
    {
        return $this->hasMany(Process::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }
}
