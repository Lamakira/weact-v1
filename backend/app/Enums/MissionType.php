<?php

declare(strict_types=1);

namespace App\Enums;

enum MissionType: string
{
    case Publicite = 'publicite';
    case Film = 'film';
    case CourtMetrage = 'court_metrage';
    case ClipMusical = 'clip_musical';
    case ShootingPhoto = 'shooting_photo';
    case Autre = 'autre';

    /**
     * Get the display name in French for this mission type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Publicite => 'Publicité',
            self::Film => 'Film',
            self::CourtMetrage => 'Court-métrage',
            self::ClipMusical => 'Clip musical',
            self::ShootingPhoto => 'Shooting photo',
            self::Autre => 'Autre',
        };
    }

    /**
     * Get all enum values as an array of strings.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
