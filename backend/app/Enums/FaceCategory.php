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
    case MODELE_PHOTO = 'modele_photo';
    case EGERIE = 'egerie';

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
            self::MODELE_PHOTO => 'Modèle Photo',
            self::EGERIE => 'Égérie',
        };
    }
}
