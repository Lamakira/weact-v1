<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CandidatureStatus;
use App\Enums\DateChangeResponseStatus;
use App\Enums\DateChangeStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\DateChangeRequest;
use App\Models\DateChangeResponse;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\User;
use App\Services\DateChange\AgendaConflictChecker;
use App\Services\DateChange\BookingDateChangeService;
use Carbon\CarbonInterface;

/**
 * Charges utiles `date_change` exposées par les ressources Mission (détail Producteur, page Face)
 * et Booking (détail et réponses des actions). Dates en `Y-m-d`, instants en ISO 8601.
 */
final class DateChangePayload
{
    /**
     * Détail Producteur d'une mission : peut-il changer la date, quota, dernière demande et réponses.
     *
     * @return array<string, mixed>
     */
    public static function forProducer(Mission $mission): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, DateChangeRequest> $requests */
        $requests = DateChangeRequest::query()
            ->where('mission_id', $mission->id)
            ->orderByDesc('id')
            ->get();

        $used = $requests->count();
        $hasPending = $requests->contains(fn (DateChangeRequest $r): bool => $r->isPending());
        $eligible = self::missionEligible($mission);

        $canChange = $eligible
            && $mission->date_tournage !== null
            && DateChangeRules::canStillRequest($mission->date_tournage)
            && $used < DateChangeRules::MAX_CHANGES
            && ! $hasPending;

        $latest = $requests->first();
        $latestPayload = null;

        if ($latest !== null && ! self::dayPassed($mission->date_tournage)) {
            $latest->load('responses.candidature.face');

            $latestPayload = [
                'old_date' => $latest->old_date->toDateString(),
                'new_date' => $latest->new_date->toDateString(),
                'reason_label' => $latest->reason->label(),
                'status' => $latest->status->value,
                'respond_by' => $latest->respond_by->toIso8601String(),
                'responses' => $latest->responses->map(fn (DateChangeResponse $response): array => [
                    'candidature_uuid' => $response->candidature?->uuid,
                    'face_name' => $response->candidature?->face?->display_name,
                    'status' => $response->status->value,
                    'responded_at' => $response->responded_at?->toIso8601String(),
                ])->values()->all(),
            ];
        }

        return [
            'can_change' => $canChange,
            'used' => $used,
            'max' => DateChangeRules::MAX_CHANGES,
            'max_date' => $mission->date_tournage !== null ? DateChangeRules::maxDate($mission->date_tournage) : null,
            // Faces qui devraient confirmer si la date changeait : utile seulement tant qu'un changement est possible.
            'faces_to_confirm' => $canChange ? self::facesToConfirm($mission) : 0,
            'pending' => $latestPayload !== null && $latestPayload['status'] === DateChangeStatus::Pending->value,
            'latest' => $latestPayload,
        ];
    }

    /**
     * Page mission Face : la demande la plus récente qui porte une ligne pour sa candidature, sinon null.
     *
     * @return array<string, mixed>|null
     */
    public static function forFace(Mission $mission, Candidature $candidature, User $faceUser): ?array
    {
        /** @var DateChangeResponse|null $row */
        $row = DateChangeResponse::query()
            ->where('candidature_id', $candidature->id)
            ->orderByDesc('date_change_request_id')
            ->with('request')
            ->first();

        $request = $row?->request;

        if ($row === null || $request === null) {
            return null;
        }

        $pending = $row->status === DateChangeResponseStatus::Pending && $request->isPending();

        return [
            'old_date' => $request->old_date->toDateString(),
            'new_date' => $request->new_date->toDateString(),
            'reason_label' => $request->reason->label(),
            'respond_by' => $request->respond_by->toIso8601String(),
            'status' => $row->status->value,
            'conflict' => $pending
                && app(AgendaConflictChecker::class)->hasConflict($faceUser->id, $request->new_date, null, null, $mission->id),
            'can_respond' => $pending && now()->lt($request->respond_by),
        ];
    }

    /**
     * Détail d'un booking pour son lecteur (l'une des deux parties).
     *
     * @return array<string, mixed>
     */
    public static function forBooking(Booking $booking, User $viewer): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, DateChangeRequest> $requests */
        $requests = DateChangeRequest::query()
            ->where('booking_id', $booking->id)
            ->orderByDesc('id')
            ->get();

        $used = $requests->count();
        $latest = $requests->first();
        $hasPending = $requests->contains(fn (DateChangeRequest $r): bool => $r->isPending());

        $canPropose = BookingDateChangeService::isEligible($booking)
            && $booking->date_debut !== null
            && DateChangeRules::canStillRequest($booking->date_debut)
            && $used < DateChangeRules::MAX_CHANGES
            && ! $hasPending;

        $latestPayload = null;

        if ($latest !== null && BookingDateChangeService::isEligible($booking) && ! self::dayPassed($booking->date_debut)) {
            $mine = $latest->requested_by_user_id === $viewer->id;
            $pending = $latest->isPending();

            $latestPayload = [
                'old_date' => $latest->old_date->toDateString(),
                'new_date' => $latest->new_date->toDateString(),
                'reason_label' => $latest->reason->label(),
                'status' => $latest->status->value,
                'respond_by' => $latest->respond_by->toIso8601String(),
                'proposed_by_me' => $mine,
                'can_respond' => $pending && ! $mine && now()->lt($latest->respond_by),
                'can_withdraw' => $pending && $mine,
                'conflict' => $pending
                    && app(AgendaConflictChecker::class)->hasConflict(
                        (int) $booking->face_id,
                        $latest->new_date,
                        $latest->new_end_date ?? $latest->new_date,
                        $booking->id,
                    ),
            ];
        }

        return [
            'can_propose' => $canPropose,
            'used' => $used,
            'max' => DateChangeRules::MAX_CHANGES,
            'max_date' => $booking->date_debut !== null ? DateChangeRules::maxDate($booking->date_debut) : null,
            'latest' => $latestPayload,
        ];
    }

    /** Mission standard payée et clôturée, avec une date de tournage : seule éligible au changement de date. */
    public static function missionEligible(Mission $mission): bool
    {
        if ($mission->type_mission === MissionType::Ugc
            || $mission->status !== MissionStatus::Closed
            || $mission->date_tournage === null) {
            return false;
        }

        // Le détail Producteur a déjà établi si un paiement est réglé (has_paid_payment) : pas de requête de plus.
        if (array_key_exists('has_paid_payment', $mission->getAttributes())) {
            return (bool) $mission->getAttribute('has_paid_payment');
        }

        return MissionPayment::query()
            ->where('mission_id', $mission->id)
            ->where('status', MissionPaymentStatus::Paid->value)
            ->exists();
    }

    /** Faces qui devraient confirmer un changement de date : confirmées ou en cours, séquestre en place. */
    private static function facesToConfirm(Mission $mission): int
    {
        return MissionPaymentCandidature::query()
            ->where('escrow_status', EscrowStatus::Locked->value)
            ->whereNull('freed_at')
            ->whereHas('missionPayment', fn ($payment) => $payment->where('mission_id', $mission->id))
            ->whereHas('candidature', fn ($candidature) => $candidature->whereIn('status', [
                CandidatureStatus::Confirmed->value,
                CandidatureStatus::InProgress->value,
            ]))
            ->count();
    }

    /** Le jour est entièrement passé (fuseau métier) : la dernière demande n'est plus exposée. */
    private static function dayPassed(?CarbonInterface $date): bool
    {
        return $date !== null && now()->greaterThanOrEqualTo(DateChangeRules::dayStart($date)->addDay());
    }
}
