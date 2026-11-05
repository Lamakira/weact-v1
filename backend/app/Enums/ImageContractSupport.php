<?php

declare(strict_types=1);

namespace App\Enums;

enum ImageContractSupport: string
{
    case Social = 'social';
    case Web = 'web';
    case Tv = 'tv';
    case Print = 'print';
    case Cinema = 'cinema';
    case All = 'all';

    public function label(): string
    {
        return match ($this) {
            self::Social => 'Réseaux sociaux',
            self::Web => 'Site web',
            self::Tv => 'Télévision',
            self::Print => 'Affichage',
            self::Cinema => 'Cinéma',
            self::All => 'Tous supports',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
