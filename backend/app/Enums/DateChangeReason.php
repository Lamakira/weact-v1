<?php

declare(strict_types=1);

namespace App\Enums;

/** Motif d'un changement de date (liste fermée : pas de texte libre, le chat est fermé avant paiement). */
enum DateChangeReason: string
{
    case LieuIndisponible = 'lieu_indisponible';
    case EquipeIndisponible = 'equipe_indisponible';
    case Meteo = 'meteo';
    case ImprevuPersonnel = 'imprevu_personnel';
    case Autre = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::LieuIndisponible => 'Lieu indisponible',
            self::EquipeIndisponible => 'Équipe indisponible',
            self::Meteo => 'Météo',
            self::ImprevuPersonnel => 'Imprévu personnel',
            self::Autre => 'Autre',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
