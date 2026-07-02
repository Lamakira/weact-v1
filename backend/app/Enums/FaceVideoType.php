<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Stored portfolio video types. Presentation video is NOT here — it remains a
 * scalar column on `faces` (capped at 1 for every tier).
 */
enum FaceVideoType: string
{
    case Acting = 'acting';
    case Ugc = 'ugc';

    public function label(): string
    {
        return match ($this) {
            self::Acting => 'Acting',
            self::Ugc => 'UGC',
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
