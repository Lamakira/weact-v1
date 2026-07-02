<?php

declare(strict_types=1);

namespace App\Enums;

enum UgcRefundReason: string
{
    case Refused = 'refused';
    case AcceptanceWindowExpired = 'acceptance_window_expired';
    case MissionDeadlineExpired = 'mission_deadline_expired';

    public function label(): string
    {
        return match ($this) {
            self::Refused => 'Deal refusé par la Face',
            self::AcceptanceWindowExpired => "Fenêtre d'acceptation expirée",
            self::MissionDeadlineExpired => 'Mission terminée sans participant',
        };
    }
}
