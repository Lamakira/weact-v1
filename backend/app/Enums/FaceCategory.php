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
    case VOIX_OFF = 'voix_off';

    /**
     * Get the French display name for this category.
     *
     * Avec un sexe connu féminin, le libellé est accordé (« Actrice », « Créatrice de contenu »).
     * Sexe inconnu, homme ou autre : forme actuelle. Les libellés invariables restent identiques.
     */
    public function label(?FaceGender $gender = null): string
    {
        if ($gender === FaceGender::FEMME) {
            return match ($this) {
                self::ACTEUR => 'Actrice',
                self::INFLUENCEUR => 'Influenceuse',
                self::CREATEUR => 'Créatrice de contenu',
                self::FIGURANT => 'Figurante',
                default => $this->label(),
            };
        }

        return match ($this) {
            self::ACTEUR => 'Acteur',
            self::INFLUENCEUR => 'Influenceur',
            self::CREATEUR => 'Créateur de contenu',
            self::MANNEQUIN => 'Mannequin',
            self::FIGURANT => 'Figurant',
            self::MODELE_PHOTO => 'Modèle Photo',
            self::EGERIE => 'Égérie',
            self::VOIX_OFF => 'Voix off',
        };
    }
}
