<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Règles communes du changement de date (mission payée et booking).
 *
 * Les jours sont des dates sans heure : le début de journée est minuit en fuseau métier,
 * exprimé en UTC (comme Mission::shootingDayStartsAt()).
 */
final class DateChangeRules
{
    /** Délai laissé à l'autre partie (ou aux Faces) pour répondre. */
    public const RESPONSE_WINDOW_HOURS = 48;

    /** Préavis minimal : demande possible jusqu'à la veille à midi, nouvelle date à 12 h au moins. */
    public const MIN_NOTICE_HOURS = 12;

    /** Nombre maximal de demandes par mission ou booking (retirées incluses). */
    public const MAX_CHANGES = 2;

    /** Report maximal par rapport à la date actuelle (mission payée et booking). */
    public const MAX_POSTPONE_DAYS = 30;

    /** Marge avant le début de la nouvelle date : le cron doit passer avant que le pointage ne soit possible à minuit. */
    public const MISSION_RESPOND_MARGIN_HOURS = 1;

    /** Début du jour (minuit fuseau métier) en UTC, à partir d'une date `Y-m-d` ou d'un instant. */
    public static function dayStart(CarbonInterface|string $date): Carbon
    {
        $day = $date instanceof CarbonInterface ? $date->toDateString() : substr($date, 0, 10);

        return Carbon::parse($day, (string) config('app.business_timezone'))->startOfDay()->utc();
    }

    /** Dernier instant pour demander un changement : la veille du tournage à midi (fuseau métier). */
    public static function requestDeadline(CarbonInterface|string $oldDate): Carbon
    {
        return self::dayStart($oldDate)->subHours(self::MIN_NOTICE_HOURS);
    }

    public static function canStillRequest(CarbonInterface|string $oldDate): bool
    {
        return now()->lt(self::requestDeadline($oldDate));
    }

    /** Échéance de réponse : le plus tôt de maintenant + 48 h et des débuts de journée fournis. */
    public static function respondBy(CarbonInterface|string $newDate, CarbonInterface|string|null $oldDate = null): Carbon
    {
        $deadline = now()->addHours(self::RESPONSE_WINDOW_HOURS);

        foreach ([$newDate, $oldDate] as $date) {
            if ($date === null) {
                continue;
            }

            $start = self::dayStart($date);

            if ($start->lessThan($deadline)) {
                $deadline = $start;
            }
        }

        return $deadline;
    }

    /** Échéance de réponse d'une mission : le plus tôt de maintenant + 48 h et d'une heure avant le début de la nouvelle date. */
    public static function missionRespondBy(CarbonInterface|string $newDate): Carbon
    {
        $deadline = now()->addHours(self::RESPONSE_WINDOW_HOURS);
        $beforeStart = self::dayStart($newDate)->subHours(self::MISSION_RESPOND_MARGIN_HOURS);

        return $beforeStart->lessThan($deadline) ? $beforeStart : $deadline;
    }

    /** Dernière date acceptée : ancienne date + 30 jours (`Y-m-d`). */
    public static function maxDate(CarbonInterface|string $oldDate): string
    {
        $day = $oldDate instanceof CarbonInterface ? $oldDate->toDateString() : substr($oldDate, 0, 10);

        return Carbon::parse($day)->addDays(self::MAX_POSTPONE_DAYS)->toDateString();
    }

    /**
     * Garde commune des demandes (mission et booking) : préavis, nouvelle date, demande en cours, quota.
     * Toutes les erreurs sont des 422 sous la clé `date`.
     */
    public static function assertCanRequest(CarbonInterface|string $oldDate, string $newDate, int $used, bool $hasPending): void
    {
        if (! self::canStillRequest($oldDate)) {
            self::fail('Il est trop tard pour changer la date : la demande doit être faite au plus tard la veille du tournage à midi.');
        }

        if ($hasPending) {
            self::fail('Une demande de changement de date est déjà en cours.');
        }

        if ($used >= self::MAX_CHANGES) {
            self::fail('Le nombre maximal de '.self::MAX_CHANGES.' changements de date est atteint.');
        }

        $oldDay = $oldDate instanceof CarbonInterface ? $oldDate->toDateString() : substr($oldDate, 0, 10);

        if (substr($newDate, 0, 10) === $oldDay) {
            self::fail('La nouvelle date doit être différente de la date actuelle.');
        }

        if (substr($newDate, 0, 10) > self::maxDate($oldDate)) {
            self::fail('La nouvelle date ne peut pas dépasser de plus de '.self::MAX_POSTPONE_DAYS.' jours la date actuelle.');
        }

        if (self::dayStart($newDate)->lessThan(now()->addHours(self::MIN_NOTICE_HOURS))) {
            self::fail('La nouvelle date est trop proche : le tournage doit commencer dans plus de '.self::MIN_NOTICE_HOURS.' h.');
        }
    }

    public static function fail(string $message): never
    {
        throw ValidationException::withMessages(['date' => [$message]]);
    }

    /** Jour affiché, par exemple « vendredi 20 novembre » (fuseau métier, français). */
    public static function formatDay(CarbonInterface|string $date): string
    {
        $day = $date instanceof CarbonInterface ? $date->toDateString() : substr($date, 0, 10);

        return Carbon::parse($day, (string) config('app.business_timezone'))
            ->locale('fr')
            ->translatedFormat('l j F');
    }

    /** Échéance affichée, par exemple « dimanche 8 novembre à 10:00 » (fuseau métier, français). */
    public static function formatDeadline(CarbonInterface $instant): string
    {
        $local = $instant->copy()->setTimezone((string) config('app.business_timezone'))->locale('fr');

        return $local->translatedFormat('l j F').' à '.$local->format('H:i');
    }

    /** Heures restantes avant l'échéance, arrondies au supérieur (au moins 1 tant qu'elle n'est pas passée). */
    public static function hoursLeft(CarbonInterface $respondBy): int
    {
        $minutes = now()->diffInMinutes($respondBy, false);

        return max(0, (int) ceil($minutes / 60));
    }
}
