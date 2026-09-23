<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasApiTokens, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function hasRole(UserRole $role): bool
    {
        return $this->role === $role;
    }

    public function hasAnyRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    // Analyst + Admin: operational actions
    public function canRefresh(): bool
    {
        return $this->hasAnyRole(UserRole::Admin, UserRole::Analyst);
    }

    public function canAcknowledgeAlerts(): bool
    {
        return $this->hasAnyRole(UserRole::Admin, UserRole::Analyst);
    }

    public function canExportReports(): bool
    {
        return $this->hasAnyRole(UserRole::Admin, UserRole::Analyst);
    }

    // Analyst helper check
    public function isAnalyst(): bool
    {
        return $this->hasRole(UserRole::Analyst);
    }

    // Admin only: system administration
    public function canManageUsers(): bool
    {
        return $this->hasRole(UserRole::Admin);
    }

    public function canDeleteAlerts(): bool
    {
        return $this->hasRole(UserRole::Admin);
    }
}
