<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    protected $fillable = [
        'hostname',
        'ip_address',
        'last_seen',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'last_seen' => 'datetime',
        ];
    }
}