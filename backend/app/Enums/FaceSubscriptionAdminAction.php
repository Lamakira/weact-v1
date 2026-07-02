<?php

declare(strict_types=1);

namespace App\Enums;

enum FaceSubscriptionAdminAction: string
{
    case ManualActivate = 'manual_activate';
    case Extend = 'extend';
    case Cancel = 'cancel';
    case CorrectDates = 'correct_dates';
    case ChangeTier = 'change_tier';

    /**
     * Get the display name in French for this admin action.
     */
    public function label(): string
    {
        return match ($this) {
            self::ManualActivate => 'Activation manuelle',
            self::Extend => 'Prolongation',
            self::Cancel => 'Annulation',
            self::CorrectDates => 'Correction des dates',
            self::ChangeTier => 'Changement de palier',
        };
    }

    /**
     * Get all enum values as an array of strings.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
