<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Process extends Model
{
    protected $fillable = [
        'monitoring_snapshot_id',
        'pid',
        'name',
        'path',
        'cpu_percent',
        'memory_mb',
        'status',
        'hash',
        'first_seen',
        'risk_level',
        'virus_total_data',
    ];

    protected $casts = [
        'virus_total_data' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(MonitoringSnapshot::class, 'monitoring_snapshot_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }
}
