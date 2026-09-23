<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessList extends Model
{
    protected $fillable = [
        'type',
        'match_by',
        'value',
        'process_name',
        'reason',
        'added_by',
    ];

    public const TYPE_WHITELIST = 'whitelist';
    public const TYPE_BLACKLIST = 'blacklist';

    public const MATCH_NAME = 'name';
    public const MATCH_HASH = 'hash';
    public const MATCH_PATH = 'path';

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * Check if a process matches any whitelist entry.
     * Returns the matching rule or null.
     */
    public static function isWhitelisted(string $name, ?string $hash, ?string $path): ?self
    {
        return static::where('type', self::TYPE_WHITELIST)
            ->where(function ($q) use ($name, $hash, $path) {
                $q->where(fn($q) => $q->where('match_by', 'name')->whereRaw('LOWER(value) = ?', [strtolower($name)]))
                  ->orWhere(fn($q) => $hash ? $q->where('match_by', 'hash')->where('value', $hash) : $q->whereRaw('1=0'))
                  ->orWhere(fn($q) => $path ? $q->where('match_by', 'path')->whereRaw('LOWER(value) = ?', [strtolower($path)]) : $q->whereRaw('1=0'));
            })
            ->first();
    }

    /**
     * Check if a process matches any blacklist entry.
     * Returns the matching rule or null.
     */
    public static function isBlacklisted(string $name, ?string $hash, ?string $path): ?self
    {
        return static::where('type', self::TYPE_BLACKLIST)
            ->where(function ($q) use ($name, $hash, $path) {
                $q->where(fn($q) => $q->where('match_by', 'name')->whereRaw('LOWER(value) = ?', [strtolower($name)]))
                  ->orWhere(fn($q) => $hash ? $q->where('match_by', 'hash')->where('value', $hash) : $q->whereRaw('1=0'))
                  ->orWhere(fn($q) => $path ? $q->where('match_by', 'path')->whereRaw('LOWER(value) = ?', [strtolower($path)]) : $q->whereRaw('1=0'));
            })
            ->first();
    }
}
