<?php

declare(strict_types=1);

namespace App\Enums;

enum MissionGender: string
{
    case Homme = 'homme';
    case Femme = 'femme';
    case Tous = 'tous';

    /**
     * Get the display name in French for this gender preference.
     */
    public function label(): string
    {
        return match ($this) {
            self::Homme => 'Homme',
            self::Femme => 'Femme',
            self::Tous => 'Homme et Femme',
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
