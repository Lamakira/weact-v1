<?php

declare(strict_types=1);

namespace App\Enums;

enum BookingCancellationReason: string
{
    case ScheduleConflict = 'schedule_conflict';
    case AcceptanceExpired = 'acceptance_expired';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ScheduleConflict => 'Conflit d\'agenda',
            self::AcceptanceExpired => 'Durée d\'acceptation dépassée',
            self::Other => 'Autre raison',
        };
    }
}
