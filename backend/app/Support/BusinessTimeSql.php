<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Expressions SQL pour les instants « jour calendaire métier + heure » (ex. 08 h le jour du
 * tournage) que les modèles calculent en PHP : les filtres « À traiter » doivent trancher
 * côté base pour rester paginables. Les colonnes `date` / datetime à 00:00 UTC portent un jour
 * calendaire métier ; l'instant UTC correspondant est ce jour + l'heure locale − le décalage
 * du fuseau métier (Africa/Porto-Novo : UTC+1 fixe, sans heure d'été).
 */
final class BusinessTimeSql
{
    /** Décalage du fuseau métier par rapport à UTC, en secondes (maintenant). */
    public static function offsetSeconds(): int
    {
        return Carbon::now((string) config('app.business_timezone'))->utcOffset() * 60;
    }

    /**
     * Instant UTC où il est `$hour` h, heure métier, le jour calendaire porté par `$column`.
     * `$column` est un identifiant de colonne écrit en dur par l'appelant (jamais une saisie).
     */
    public static function localInstant(string $column, int $hour = 0): string
    {
        $seconds = $hour * 3600 - self::offsetSeconds();

        return "DATE_ADD(DATE({$column}), INTERVAL {$seconds} SECOND)";
    }
}
