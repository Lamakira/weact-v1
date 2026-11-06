<?php

declare(strict_types=1);

namespace App\Services\DateChange;

use App\Enums\CandidatureStatus;
use App\Enums\DateChangeReason;
use App\Enums\DateChangeResponseStatus;
use App\Enums\DateChangeStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Models\Candidature;
use App\Models\DateChangeRequest;
use App\Models\DateChangeResponse;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\User;
use App\Services\MissionPaymentService;
use App\Support\DateChangeRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Changement de date d'une mission standard payée. La date change tout de suite ; chaque Face
 * confirmée ou en cours confirme ou se retire (silence à l'échéance = retrait, sans pénalité).
 * Les Faces seulement acceptées sont prévenues : leur confirmation habituelle vaut accord.
 *
 * Ordre de verrous (aligné sur expireUnconfirmedFace) : payment, entries, mission, candidature,
 * puis demande et ligne de réponse.
 */
class MissionDateChangeService
{
    public function __construct(
        private readonly MissionPaymentService $payments,
        private readonly DateChangeNotifier $notifier,
        private readonly AgendaConflictChecker $agenda,
    ) {}

    /**
     * Le Producteur change la date de tournage d'une mission payée.
     */
    public function request(Mission $mission, string $newDate, DateChangeReason $reason, User $producerUser): Mission
    {
        $paymentId = MissionPayment::query()->where('mission_id', $mission->id)->value('id');

        if ($paymentId === null) {
            DateChangeRules::fail('Le changement de date est possible uniquement pour une mission payée.');
        }

        /** @var array{request: DateChangeRequest, mission: Mission, pending: list<Candidature>, accepted: list<Candidature>} $outcome */
        $outcome = DB::transaction(function () use ($mission, $newDate, $reason, $producerUser, $paymentId): array {
            $this->payments->lockCashSelection((int) $paymentId);

            /** @var MissionPayment|null $payment */
            $payment = MissionPayment::query()->find($paymentId);
            /** @var Mission $locked */
            $locked = Mission::query()->lockForUpdate()->findOrFail($mission->id);

            if ($locked->type_mission === MissionType::Ugc) {
                DateChangeRules::fail('Le changement de date n\'est pas disponible pour une mission UGC.');
            }

            if ($payment === null
                || $payment->status !== MissionPaymentStatus::Paid
                || $locked->status !== MissionStatus::Closed
                || $locked->date_tournage === null) {
                DateChangeRules::fail('Le changement de date est possible uniquement pour une mission payée.');
            }

            $used = DateChangeRequest::query()->where('mission_id', $locked->id)->count();
            $hasPending = DateChangeRequest::query()
                ->where('mission_id', $locked->id)
                ->where('status', DateChangeStatus::Pending->value)
                ->exists();

            DateChangeRules::assertCanRequest($locked->date_tournage, $newDate, $used, $hasPending);

            $entries = MissionPaymentCandidature::query()
                ->where('mission_payment_id', $payment->id)
                ->where('escrow_status', EscrowStatus::Locked->value)
                ->whereNull('freed_at')
                ->get();

            $candidatures = Candidature::query()
                ->whereIn('id', $entries->pluck('candidature_id'))
                ->lockForUpdate()
                ->get();

            $toConfirm = $candidatures
                ->filter(fn (Candidature $c): bool => in_array($c->status, [CandidatureStatus::Confirmed, CandidatureStatus::InProgress], true))
                ->values();
            $accepted = $candidatures
                ->filter(fn (Candidature $c): bool => $c->status === CandidatureStatus::Accepted)
                ->values();

            $request = DateChangeRequest::query()->create([
                'mission_id' => $locked->id,
                'requested_by_user_id' => $producerUser->id,
                'requested_by_role' => 'producer',
                'old_date' => $locked->date_tournage->toDateString(),
                'new_date' => $newDate,
                'reason' => $reason,
                // Aucune Face à consulter : la demande est résolue d'emblée.
                'status' => $toConfirm->isEmpty() ? DateChangeStatus::Completed : DateChangeStatus::Pending,
                'respond_by' => DateChangeRules::missionRespondBy($newDate),
                'resolved_at' => $toConfirm->isEmpty() ? now() : null,
            ]);

            foreach ($toConfirm as $candidature) {
                DateChangeResponse::query()->create([
                    'date_change_request_id' => $request->id,
                    'candidature_id' => $candidature->id,
                    'status' => DateChangeResponseStatus::Pending,
                ]);
            }

            $locked->update([
                'date_tournage' => $newDate,
                'shooting_reminder_sent_at' => null,
            ]);

            return [
                'request' => $request,
                'mission' => $locked,
                'pending' => $toConfirm->all(),
                'accepted' => $accepted->all(),
            ];
        });

        $this->notifyRequested($outcome['mission'], $outcome['request'], $outcome['pending'], $outcome['accepted']);

        return $outcome['mission']->fresh() ?? $outcome['mission'];
    }

    /**
     * Réponse d'une Face confirmée ou en cours : `confirm` ou `withdraw`.
     */
    public function respond(Candidature $candidature, string $decision): void
    {
        /** @var DateChangeResponse|null $row */
        $row = DateChangeResponse::query()
            ->where('candidature_id', $candidature->id)
            ->latest('id')
            ->first();

        if ($row === null) {
            DateChangeRules::fail('Aucune demande de changement de date n\'attend votre réponse.');
        }

        if ($row->status !== DateChangeResponseStatus::Pending) {
            DateChangeRules::fail('Vous avez déjà répondu à cette demande.');
        }

        if ($decision === 'withdraw') {
            $this->withdraw($candidature, $row);

            return;
        }

        $this->confirm($candidature, $row);
    }

    private function confirm(Candidature $candidature, DateChangeResponse $row): void
    {
        /** @var MissionPaymentCandidature|null $entryRef */
        $entryRef = MissionPaymentCandidature::query()
            ->where('candidature_id', $candidature->id)
            ->whereNotNull('mission_payment_id')
            ->first();

        if ($entryRef === null) {
            DateChangeRules::fail('Votre participation n\'est plus active sur cette mission.');
        }

        $finalized = DB::transaction(function () use ($candidature, $row, $entryRef): ?DateChangeRequest {
            $this->payments->lockCashSelection((int) $entryRef->mission_payment_id);

            /** @var Mission $mission */
            $mission = Mission::query()->lockForUpdate()->findOrFail($candidature->mission_id);
            /** @var Candidature $locked */
            $locked = Candidature::query()->lockForUpdate()->findOrFail($candidature->id);
            /** @var MissionPaymentCandidature|null $entry */
            $entry = MissionPaymentCandidature::query()->find($entryRef->id);

            [$request, $response] = $this->lockRespondable($row);

            if (! in_array($locked->status, [CandidatureStatus::Confirmed, CandidatureStatus::InProgress], true)
                || $entry === null
                || $entry->freed_at !== null
                || $entry->escrow_status !== EscrowStatus::Locked) {
                DateChangeRules::fail('Votre participation n\'est plus active sur cette mission.');
            }

            $faceUserId = $this->faceUserId($locked);

            if ($faceUserId !== null
                && $this->agenda->hasConflict($faceUserId, $request->new_date, null, null, $mission->id)) {
                DateChangeRules::fail('Vous avez déjà un engagement à cette date : vous ne pouvez pas confirmer.');
            }

            $response->update([
                'status' => DateChangeResponseStatus::Confirmed,
                'responded_at' => now(),
            ]);

            return $this->finalizeIfResolved($request);
        });

        if ($finalized !== null) {
            $this->notifySummary($finalized);
        }
    }

    private function withdraw(Candidature $candidature, DateChangeResponse $row): void
    {
        $finalized = null;

        $withdrawn = $this->payments->withdrawFaceAfterDateChange(
            $candidature,
            'date_change_withdrawn',
            function (Candidature $locked, Mission $mission) use ($row, &$finalized): bool {
                [$request, $response] = $this->lockRespondable($row);

                $response->update([
                    'status' => DateChangeResponseStatus::Withdrawn,
                    'responded_at' => now(),
                ]);

                $finalized = $this->finalizeIfResolved($request);

                return true;
            },
        );

        if (! $withdrawn) {
            $fresh = $row->fresh();

            DateChangeRules::fail($fresh !== null && $fresh->status !== DateChangeResponseStatus::Pending
                ? 'Vous avez déjà répondu à cette demande.'
                : 'Votre participation n\'est plus active sur cette mission.');
        }

        if ($finalized !== null) {
            $this->notifySummary($finalized);
        }
    }

    /**
     * Verrouille la demande et la ligne, et vérifie qu'on peut encore y répondre (en cours, avant l'échéance).
     *
     * @return array{0: DateChangeRequest, 1: DateChangeResponse}
     */
    private function lockRespondable(DateChangeResponse $row): array
    {
        /** @var DateChangeRequest $request */
        $request = DateChangeRequest::query()->lockForUpdate()->findOrFail($row->date_change_request_id);
        /** @var DateChangeResponse $response */
        $response = DateChangeResponse::query()->lockForUpdate()->findOrFail($row->id);

        if ($response->status !== DateChangeResponseStatus::Pending) {
            DateChangeRules::fail('Vous avez déjà répondu à cette demande.');
        }

        if (! $request->isPending()) {
            DateChangeRules::fail('Cette demande de changement de date n\'est plus en cours.');
        }

        if (now()->greaterThanOrEqualTo($request->respond_by)) {
            DateChangeRules::fail('Le délai pour répondre à ce changement de date est dépassé.');
        }

        return [$request, $response];
    }

    /**
     * Passe la demande en `completed` quand plus aucune ligne n'attend. À appeler dans la transaction.
     */
    private function finalizeIfResolved(DateChangeRequest $request): ?DateChangeRequest
    {
        if (! $request->isPending()
            || $request->responses()->where('status', DateChangeResponseStatus::Pending->value)->exists()) {
            return null;
        }

        $request->update([
            'status' => DateChangeStatus::Completed,
            'resolved_at' => now(),
        ]);

        return $request;
    }

    // -------------------------------------------------------------------------
    // Cron (date-changes:process)
    // -------------------------------------------------------------------------

    /**
     * Relance unique à mi-parcours des Faces qui n'ont pas répondu.
     *
     * @return int nombre de demandes relancées
     */
    public function sendReminders(): int
    {
        $sent = 0;

        $requests = DateChangeRequest::query()
            ->whereNotNull('mission_id')
            ->where('status', DateChangeStatus::Pending->value)
            ->whereNull('reminder_sent_at')
            ->where('respond_by', '>', now())
            ->get()
            ->filter(function (DateChangeRequest $request): bool {
                $halfway = $request->created_at->copy()->addSeconds((int) floor($request->created_at->diffInSeconds($request->respond_by) / 2));

                return now()->greaterThanOrEqualTo($halfway);
            });

        foreach ($requests as $request) {
            try {
                // Réservation atomique : une exécution concurrente ou répétée ne relance pas deux fois.
                $claimed = DateChangeRequest::query()
                    ->whereKey($request->id)
                    ->where('status', DateChangeStatus::Pending->value)
                    ->whereNull('reminder_sent_at')
                    ->update(['reminder_sent_at' => now()]);

                if ($claimed === 0) {
                    continue;
                }

                $this->notifyReminder($request);
                $sent++;
            } catch (\Throwable $e) {
                Log::critical('Relance de changement de date échouée', [
                    'date_change_request_id' => $request->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * Expire les demandes dont l'échéance est passée : chaque ligne sans réponse devient `expired` avec retrait.
     *
     * @return array{processed: int, failures: int}
     */
    public function expireDue(): array
    {
        $processed = 0;
        $failures = 0;

        $requests = DateChangeRequest::query()
            ->whereNotNull('mission_id')
            ->where('status', DateChangeStatus::Pending->value)
            ->where('respond_by', '<=', now())
            ->get();

        foreach ($requests as $request) {
            try {
                $this->expireRequest($request);
                $processed++;
            } catch (\Throwable $e) {
                $failures++;
                Log::critical('Expiration de changement de date de mission échouée', [
                    'date_change_request_id' => $request->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['processed' => $processed, 'failures' => $failures];
    }

    private function expireRequest(DateChangeRequest $request): void
    {
        $rows = $request->responses()
            ->where('status', DateChangeResponseStatus::Pending->value)
            ->get();

        $rowFailure = null;

        foreach ($rows as $row) {
            try {
                $this->expireRow($row);
            } catch (\Throwable $e) {
                $rowFailure ??= $e;
                Log::critical('Retrait après changement de date échoué pour une candidature', [
                    'date_change_request_id' => $request->id,
                    'candidature_id' => $row->candidature_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($rowFailure !== null) {
            throw $rowFailure;
        }

        // Demande sans ligne restante (ou déjà vidée) : finalisation de sécurité.
        $finalized = DB::transaction(function () use ($request): ?DateChangeRequest {
            /** @var DateChangeRequest|null $locked */
            $locked = DateChangeRequest::query()->lockForUpdate()->find($request->id);

            return $locked !== null ? $this->finalizeIfResolved($locked) : null;
        });

        if ($finalized !== null) {
            $this->notifySummary($finalized);
        }
    }

    private function expireRow(DateChangeResponse $row): void
    {
        /** @var Candidature $candidature */
        $candidature = Candidature::query()->findOrFail($row->candidature_id);
        $finalized = null;

        $withdrawn = $this->payments->withdrawFaceAfterDateChange(
            $candidature,
            'date_change_expired',
            function (Candidature $locked, Mission $mission) use ($row, &$finalized): bool {
                /** @var DateChangeRequest $request */
                $request = DateChangeRequest::query()->lockForUpdate()->findOrFail($row->date_change_request_id);
                /** @var DateChangeResponse $response */
                $response = DateChangeResponse::query()->lockForUpdate()->findOrFail($row->id);

                if (! $request->isPending() || $response->status !== DateChangeResponseStatus::Pending) {
                    return false;
                }

                $response->update([
                    'status' => DateChangeResponseStatus::Expired,
                    'responded_at' => now(),
                ]);

                $finalized = $this->finalizeIfResolved($request);

                return true;
            },
        );

        if ($withdrawn) {
            $mission = Mission::query()->find($candidature->mission_id);

            if ($mission !== null) {
                $this->notifyExpired($mission, $candidature);
            }
        } else {
            // La Face n'est plus engagée (candidature terminée, annulée...) : rien à retirer, la ligne se ferme.
            $finalized = DB::transaction(function () use ($row): ?DateChangeRequest {
                /** @var DateChangeRequest|null $request */
                $request = DateChangeRequest::query()->lockForUpdate()->find($row->date_change_request_id);
                /** @var DateChangeResponse|null $response */
                $response = DateChangeResponse::query()->lockForUpdate()->find($row->id);

                if ($request === null || $response === null
                    || ! $request->isPending()
                    || $response->status !== DateChangeResponseStatus::Pending) {
                    return null;
                }

                $response->update([
                    'status' => DateChangeResponseStatus::Expired,
                    'responded_at' => now(),
                ]);

                return $this->finalizeIfResolved($request);
            });
        }

        if ($finalized !== null) {
            $this->notifySummary($finalized);
        }
    }

    // -------------------------------------------------------------------------
    // Notifications (toujours après commit)
    // -------------------------------------------------------------------------

    /**
     * @param  list<Candidature>  $pending
     * @param  list<Candidature>  $accepted
     */
    private function notifyRequested(Mission $mission, DateChangeRequest $request, array $pending, array $accepted): void
    {
        $date = DateChangeRules::formatDay($request->new_date);
        $limit = DateChangeRules::formatDeadline($request->respond_by);
        $message = "Le tournage de « {$mission->titre} » passe au {$date}. Confirmez avant le {$limit}.";

        foreach ($pending as $candidature) {
            $this->notifier->notifySafely(
                userId: $this->faceUserId($candidature),
                type: 'mission_date_change_requested',
                data: [
                    'message' => $message,
                    'mission_id' => $mission->id,
                    'candidature_id' => $candidature->id,
                    'respond_by' => $request->respond_by->toIso8601String(),
                    'url' => "/face/missions/{$mission->uuid}",
                ],
                mail: [
                    'subject' => "Votre tournage passe au {$date} : confirmez avant le {$limit}",
                    'heading' => 'Nouvelle date de tournage',
                    'lines' => [
                        $message,
                        'Vous pouvez confirmer votre participation ou vous retirer de la mission. Sans réponse de votre part, vous serez retiré de la mission, sans aucune pénalité.',
                    ],
                    'cta_label' => 'Répondre',
                ],
            );
        }

        $this->notifier->notifyMissionDateChanged($mission, $accepted);
    }

    private function notifyReminder(DateChangeRequest $request): void
    {
        /** @var Mission|null $mission */
        $mission = Mission::query()->find($request->mission_id);

        if ($mission === null) {
            return;
        }

        $hours = DateChangeRules::hoursLeft($request->respond_by);
        $message = "Plus que {$hours} h pour répondre à la nouvelle date de « {$mission->titre} ».";

        $candidatures = Candidature::query()
            ->whereIn('id', $request->responses()->where('status', DateChangeResponseStatus::Pending->value)->pluck('candidature_id'))
            ->get();

        foreach ($candidatures as $candidature) {
            $this->notifier->notifySafely(
                userId: $this->faceUserId($candidature),
                type: 'date_change_reminder',
                data: [
                    'message' => $message,
                    'mission_id' => $mission->id,
                    'candidature_id' => $candidature->id,
                    'respond_by' => $request->respond_by->toIso8601String(),
                    'url' => "/face/missions/{$mission->uuid}",
                ],
                mail: [
                    'subject' => "Plus que {$hours} h pour répondre à la nouvelle date",
                    'heading' => 'Réponse attendue',
                    'lines' => [$message],
                    'cta_label' => 'Répondre',
                ],
            );
        }
    }

    private function notifyExpired(Mission $mission, Candidature $candidature): void
    {
        $message = "Sans réponse de votre part, vous avez quitté la mission « {$mission->titre} ». Aucune pénalité n'a été appliquée.";

        $this->notifier->notifySafely(
            userId: $this->faceUserId($candidature),
            type: 'mission_date_change_expired',
            data: [
                'message' => $message,
                'mission_id' => $mission->id,
                'candidature_id' => $candidature->id,
                'url' => "/face/missions/{$mission->uuid}",
            ],
            mail: [
                'subject' => "Vous avez quitté la mission « {$mission->titre} »",
                'heading' => 'Vous avez quitté la mission',
                'lines' => [$message],
                'cta_label' => 'Voir la mission',
            ],
        );
    }

    /** Résumé des réponses au Producteur, quand toutes les lignes sont résolues. */
    private function notifySummary(DateChangeRequest $request): void
    {
        /** @var Mission|null $mission */
        $mission = Mission::query()->find($request->mission_id);

        if ($mission === null) {
            return;
        }

        $confirmed = $request->responses()->where('status', DateChangeResponseStatus::Confirmed->value)->count();
        $freed = $request->responses()
            ->whereIn('status', [DateChangeResponseStatus::Withdrawn->value, DateChangeResponseStatus::Expired->value])
            ->count();

        $text = $confirmed.' '.($confirmed > 1 ? 'confirmées' : 'confirmée').', '
            .$freed.' '.($freed > 1 ? 'places libérées' : 'place libérée');
        $message = "Réponses à la nouvelle date : {$text}";

        $this->notifier->notifySafely(
            userId: $this->notifier->userIdForProducer((int) $mission->producer_id),
            type: 'mission_date_change_summary',
            data: [
                'message' => $message,
                'mission_id' => $mission->id,
                'confirmed' => $confirmed,
                'freed' => $freed,
                'url' => "/producer/missions/{$mission->uuid}",
            ],
            mail: [
                'subject' => $message,
                'heading' => 'Réponses à la nouvelle date',
                'lines' => ["Pour « {$mission->titre} » : {$text}."],
                'cta_label' => 'Voir la mission',
            ],
        );
    }

    private function faceUserId(Candidature $candidature): ?int
    {
        return $this->notifier->userIdForFace((int) $candidature->face_id);
    }
}
