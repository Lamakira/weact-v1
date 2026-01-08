<?php

declare(strict_types=1);

namespace App\Enums;

enum ProducerType: string
{
    case Agency = 'agency';
    case Particulier = 'particulier';

    /**
     * Get the display name for this producer type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Agency => 'Agence',
            self::Particulier => 'Particulier',
        };
    }
}
