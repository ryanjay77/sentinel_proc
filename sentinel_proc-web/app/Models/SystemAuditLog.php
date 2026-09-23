<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'user_name',
        'action',
        'target_type',
        'target_id',
        'target_label',
        'ip_address',
        'meta',
    ];

    protected $casts = [
        'meta'       => 'json',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record an audit entry from anywhere in the app.
     */
    public static function record(
        string $action,
        ?string $targetType = null,
        mixed $targetId = null,
        ?string $targetLabel = null,
        array $meta = []
    ): void {
        $user = auth()->user();

        static::create([
            'user_id'      => $user?->id,
            'user_name'    => $user?->name ?? 'System',
            'action'       => $action,
            'target_type'  => $targetType,
            'target_id'    => $targetId !== null ? (string) $targetId : null,
            'target_label' => $targetLabel,
            'ip_address'   => request()->ip(),
            'meta'         => empty($meta) ? null : $meta,
        ]);
    }
}
