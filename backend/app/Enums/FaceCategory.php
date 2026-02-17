<?php

declare(strict_types=1);

namespace App\Enums;

enum FaceCategory: string
{
    case ACTEUR = 'acteur';
    case INFLUENCEUR = 'influenceur';
    case CREATEUR = 'createur';
    case MANNEQUIN = 'mannequin';
    case FIGURANT = 'figurant';

    /**
     * Get the French display name for this category.
     */
    public function label(): string
    {
        return match ($this) {
            self::ACTEUR => 'Acteur',
            self::INFLUENCEUR => 'Influenceur',
            self::CREATEUR => 'Créateur de contenu',
            self::MANNEQUIN => 'Mannequin',
            self::FIGURANT => 'Figurant',
        };
    }
}
