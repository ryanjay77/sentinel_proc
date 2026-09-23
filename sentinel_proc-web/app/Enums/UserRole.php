<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Analyst = 'analyst';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Analyst => 'Security Analyst',
            self::Viewer => 'Viewer',
        };
    }
}
