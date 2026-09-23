<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class ActivityLog extends Model
{
    public $timestamps = false;

     protected $fillable = [
        'monitoring_snapshot_id',
        'process_name',
        'pid',
        'event_type',
        'risk_level',
        'risk_score',
        'path',
        'hash',
        'details',
    ];

    protected $casts = [
        'details'    => 'json',
        'created_at' => 'datetime',
    ];
}
