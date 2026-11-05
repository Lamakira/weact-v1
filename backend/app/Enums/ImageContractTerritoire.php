<?php

declare(strict_types=1);

namespace App\Enums;

enum ImageContractTerritoire: string
{
    case Benin = 'benin';
    case WestAfrica = 'west_africa';
    case World = 'world';

    public function label(): string
    {
        return match ($this) {
            self::Benin => 'Bénin',
            self::WestAfrica => 'Afrique de l\'Ouest',
            self::World => 'Monde entier',
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
