<?php

declare(strict_types=1);

namespace App\Enums;

enum CompensationType: string
{
    case Product = 'product';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Product => 'Produit seul',
            self::Hybrid => 'Produit + argent',
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
