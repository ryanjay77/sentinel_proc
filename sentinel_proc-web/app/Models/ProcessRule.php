<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcessRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'process_name',
        'type',
        'reason',
        'created_by',
    ];
}
