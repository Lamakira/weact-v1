<?php

declare(strict_types=1);

namespace App\Enums;

enum FaceGender: string
{
    case HOMME = 'homme';
    case FEMME = 'femme';
    case AUTRE = 'autre';

    /**
     * Get the French display name for this gender.
     */
    public function label(): string
    {
        return match ($this) {
            self::HOMME => 'Homme',
            self::FEMME => 'Femme',
            self::AUTRE => 'Autre',
        };
    }
}
