<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CompensationType;
use App\Enums\MissionType;
use App\Models\Mission;
use Illuminate\Support\Str;

/**
 * Public-safe wording of a mission for the « new mission » push and e-mail
 * (type, place, compensation). Never exposes private data.
 */
final class MissionAnnouncement
{
    public static function typeLabel(Mission $mission): string
    {
        if ($mission->type_mission === MissionType::Autre && filled($mission->type_mission_autre)) {
            return (string) $mission->type_mission_autre;
        }

        return $mission->type_mission?->label() ?? 'Mission';
    }

    /**
     * Cash budget (« 50 000 FCFA »), « Produit offert » or « Produit + 20 000 FCFA » (UGC).
     * Null when nothing is worth showing.
     */
    public static function compensationLabel(Mission $mission): ?string
    {
        if ($mission->type_mission === MissionType::Ugc) {
            if ($mission->type_compensation === CompensationType::Hybrid && (int) $mission->montant_remuneration > 0) {
                return 'Produit + '.self::money((int) $mission->montant_remuneration);
            }

            return 'Produit offert';
        }

        return $mission->budget > 0 ? self::money($mission->budget) : null;
    }

    public static function pushTitle(Mission $mission): string
    {
        return 'Nouvelle mission : '.Str::limit(trim($mission->titre), 60, '…');
    }

    public static function pushBody(Mission $mission): string
    {
        $parts = array_filter([
            self::typeLabel($mission),
            filled($mission->lieu) ? (string) $mission->lieu : null,
            self::compensationLabel($mission),
        ]);

        return implode(' · ', $parts);
    }

    public static function money(int $amount): string
    {
        // Espace insécable pour ne pas couper « 50 000 » en fin de ligne.
        return \App\Support\Money::format($amount);
    }
}
