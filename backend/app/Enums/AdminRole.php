<?php

declare(strict_types=1);

namespace App\Enums;

enum AdminRole: string
{
    case SuperAdmin = 'superadmin';
    case Admin = 'admin';
    case Editor = 'editor';

    /**
     * Get the display name for this admin role.
     */
    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Editor => 'Éditeur',
        };
    }
}
