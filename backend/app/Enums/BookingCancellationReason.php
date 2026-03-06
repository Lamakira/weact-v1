<?php

declare(strict_types=1);

namespace App\Enums;

enum BookingCancellationReason: string
{
    case ScheduleConflict = 'schedule_conflict';
    case PriceDisagreement = 'price_disagreement';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ScheduleConflict => 'Conflit d\'agenda',
            self::PriceDisagreement => 'Désaccord sur le prix',
            self::Other => 'Autre raison',
        };
    }
}
