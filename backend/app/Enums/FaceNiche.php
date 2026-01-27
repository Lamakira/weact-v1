<?php

declare(strict_types=1);

namespace App\Enums;

enum FaceNiche: string
{
    case BEAUTE = 'beaute';
    case NOURRITURE = 'nourriture';
    case DECOUVERTE = 'decouverte';
    case MODE = 'mode';

    /**
     * Get the French display name for this niche.
     */
    public function label(): string
    {
        return match ($this) {
            self::BEAUTE => 'Beauté',
            self::NOURRITURE => 'Nourriture',
            self::DECOUVERTE => 'Découverte',
            self::MODE => 'Mode',
        };
    }
}
