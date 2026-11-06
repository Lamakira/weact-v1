<?php

declare(strict_types=1);

namespace App\Services\DateChange;

use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Models\Booking;
use App\Models\MissionPaymentCandidature;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Agenda d'une Face : conflit avec un booking actif ou une autre mission engagée sur des jours donnés.
 */
class AgendaConflictChecker
{
    /**
     * @param  int  $faceUserId  users.id de la Face (bookings.face_id), pas faces.id
     * @param  CarbonInterface|string  $firstDay  premier jour visé (`Y-m-d` ou instant)
     * @param  CarbonInterface|string|null  $lastDay  dernier jour visé (inclus), le premier jour par défaut
     * @param  int|null  $exceptBookingId  booking ignoré (celui dont on décale la date)
     * @param  int|null  $exceptMissionId  mission ignorée (celle dont on décale la date)
     */
    public function hasConflict(
        int $faceUserId,
        CarbonInterface|string $firstDay,
        CarbonInterface|string|null $lastDay = null,
        ?int $exceptBookingId = null,
        ?int $exceptMissionId = null,
    ): bool {
        // Même convention que les contrôles de chevauchement existants : jours calendaires, début et fin de journée.
        $first = Carbon::parse($firstDay instanceof CarbonInterface ? $firstDay->toDateString() : substr($firstDay, 0, 10))->startOfDay();
        $last = $lastDay === null
            ? $first->copy()
            : Carbon::parse($lastDay instanceof CarbonInterface ? $lastDay->toDateString() : substr($lastDay, 0, 10))->startOfDay();

        $bookingConflict = Booking::query()
            ->overlappingActiveForFace($faceUserId, $first, $last->copy()->endOfDay(), $exceptBookingId)
            ->exists();

        if ($bookingConflict) {
            return true;
        }

        $faceId = User::query()->whereKey($faceUserId)->value('userable_id');

        if ($faceId === null) {
            return false;
        }

        // Autre mission engagée : séquestre en place (non libérée) et candidature acceptée, confirmée ou en cours.
        return MissionPaymentCandidature::query()
            ->where('face_id', $faceId)
            ->where('escrow_status', EscrowStatus::Locked->value)
            ->whereNull('freed_at')
            ->whereHas('candidature', fn ($candidature) => $candidature
                ->whereIn('status', [
                    CandidatureStatus::Accepted->value,
                    CandidatureStatus::Confirmed->value,
                    CandidatureStatus::InProgress->value,
                ])
                ->when($exceptMissionId !== null, fn ($q) => $q->where('mission_id', '!=', $exceptMissionId))
                ->whereHas('mission', fn ($mission) => $mission
                    ->whereDate('date_tournage', '>=', $first->toDateString())
                    ->whereDate('date_tournage', '<=', $last->toDateString())))
            ->exists();
    }
}
