<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Mail\FaceSelectedMail;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Conversation;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Lot B4 (décisions D1, D2) — place libérée par une Face sélectionnée qui n'a pas confirmé
 * (MissionPaymentService::expireUnconfirmedFace pose `freed_at`, l'argent reste en séquestre) :
 *  - replace() : le Producer donne la place à une candidature non retenue de la mission ; l'argent
 *    de la place passe sur une nouvelle entry (net recalculé au palier de la nouvelle Face, aucun
 *    débit), l'ancienne entry devient `transferred`, la remplaçante a 48 h pour confirmer (règle B3) ;
 *  - refundIfShootingDayStarted() : sans remplaçante au début du jour de tournage, budget_par_face
 *    est crédité au wallet du Producer (D1).
 * Verrous dans l'ordre de markAsPaid / B3 : payment → entries → mission → candidatures.
 */
class FreedPlaceService
{
    public function __construct(
        private readonly MissionPaymentService $missionPaymentService,
        private readonly FaceEntitlementService $faceEntitlementService,
    ) {}

    /**
     * Entries libérées d'une mission, encore en séquestre (en attente de remplaçante ou de remboursement).
     *
     * @return \Illuminate\Database\Eloquent\Builder<MissionPaymentCandidature>
     */
    public static function pendingFreedEntries(Mission $mission): \Illuminate\Database\Eloquent\Builder
    {
        return MissionPaymentCandidature::query()
            ->whereHas('missionPayment', fn ($query) => $query
                ->where('mission_id', $mission->id)
                ->where('status', MissionPaymentStatus::Paid->value))
            ->whereNotNull('freed_at')
            ->where('escrow_status', EscrowStatus::Locked->value);
    }

    /**
     * Une place libérée peut-elle encore être pourvue maintenant (mission standard clôturée, assez tôt avant le tournage) ?
     */
    public function canReplaceNow(Mission $mission, int $freedCount): bool
    {
        return $freedCount > 0
            && $mission->type_mission !== MissionType::Ugc
            && $mission->status === MissionStatus::Closed
            && ! MissionPaymentService::isTooLateToReplace($mission->shootingDayStartsAt());
    }

    /**
     * Ids des candidatures que replace() accepterait aujourd'hui (même prédicat : isEligibleReplacement),
     * vide s'il n'y a aucune place libérée pourvoyable.
     *
     * @return list<int>
     */
    public function replaceableCandidatureIds(Mission $mission): array
    {
        // Garde sans requête d'abord (liste des candidatures : pas de coût sur une mission non clôturée).
        if (! $this->canReplaceNow($mission, 1) || ! self::pendingFreedEntries($mission)->exists()) {
            return [];
        }

        return $this->missionPaymentService->replacementCandidates($mission)
            ->map(fn (Candidature $candidature): int => (int) $candidature->id)
            ->all();
    }

    /**
     * @throws ValidationException
     */
    public function replace(Mission $mission, string $candidatureUuid): Candidature
    {
        /** @var MissionPayment|null $paymentRef */
        $paymentRef = MissionPayment::query()
            ->where('mission_id', $mission->id)
            ->where('status', MissionPaymentStatus::Paid->value)
            ->first();

        if ($paymentRef === null) {
            throw ValidationException::withMessages(['place' => ['Aucune place libérée à pourvoir sur cette mission.']]);
        }

        $result = DB::transaction(function () use ($mission, $candidatureUuid, $paymentRef): array {
            $this->missionPaymentService->lockCashSelection((int) $paymentRef->id);

            /** @var MissionPayment $payment */
            $payment = MissionPayment::query()->findOrFail($paymentRef->id);
            /** @var Mission $lockedMission */
            $lockedMission = Mission::query()->lockForUpdate()->findOrFail($mission->id);

            $shootingStart = $lockedMission->shootingDayStartsAt();

            if ($lockedMission->type_mission === MissionType::Ugc
                || $lockedMission->status !== MissionStatus::Closed
                || $payment->status !== MissionPaymentStatus::Paid) {
                throw ValidationException::withMessages([
                    'place' => ['Une place libérée ne peut plus être pourvue sur cette mission (jour du tournage commencé ou mission non clôturée).'],
                ]);
            }

            // Une remplaçante a au moins FACE_CONFIRMATION_MIN_HOURS pour confirmer (plancher B3) : son échéance
            // ne doit jamais dépasser le début du jour de tournage, sinon elle resterait sans issue.
            if (MissionPaymentService::isTooLateToReplace($shootingStart)) {
                throw ValidationException::withMessages([
                    'place' => [MissionPaymentService::TOO_LATE_TO_REPLACE_MESSAGE],
                ]);
            }

            /** @var MissionPaymentCandidature|null $freedEntry */
            $freedEntry = MissionPaymentCandidature::query()
                ->where('mission_payment_id', $payment->id)
                ->whereNotNull('freed_at')
                ->where('escrow_status', EscrowStatus::Locked->value)
                ->orderBy('freed_at')
                ->orderBy('id')
                ->first();

            if ($freedEntry === null) {
                throw ValidationException::withMessages(['place' => ['Aucune place libérée à pourvoir sur cette mission.']]);
            }

            /** @var Candidature|null $replacement */
            $replacement = Candidature::query()
                ->where('uuid', $candidatureUuid)
                ->where('mission_id', $lockedMission->id)
                ->lockForUpdate()
                ->first();

            if ($replacement === null
                || $replacement->status !== CandidatureStatus::Rejected
                || MissionPaymentCandidature::query()->where('candidature_id', $replacement->id)->exists()
                || ! $this->missionPaymentService->isEligibleReplacement($replacement)) {
                throw ValidationException::withMessages([
                    'candidature_id' => ['Cette candidature ne peut pas reprendre la place : choisissez une Face non retenue, abonnée et active.'],
                ]);
            }

            /** @var Face $face */
            $face = $replacement->face;
            $budget = (int) $payment->budget_par_face;
            $montantFaceRecoit = $budget - (int) round($budget * $this->faceEntitlementService->capabilities($face)->commissionRate);

            $freedEntry->update(['escrow_status' => EscrowStatus::Transferred]);

            $newEntry = MissionPaymentCandidature::create([
                'mission_payment_id' => $payment->id,
                'candidature_id' => $replacement->id,
                'face_id' => $replacement->face_id,
                'montant_face_recoit' => $montantFaceRecoit,
                'escrow_status' => EscrowStatus::Locked,
                'attendance_status' => AttendanceStatus::Pending,
                'locked_at' => now(),
                'replacement_of_entry_id' => $freedEntry->id,
            ]);

            $replacement->update([
                'status' => CandidatureStatus::Accepted,
                'accepted_at' => now(),
                'confirmation_reminder_sent_at' => null,
            ]);

            Conversation::firstOrCreate(['candidature_id' => $replacement->id]);

            return [
                'mission' => $lockedMission,
                'candidature' => $replacement->fresh(),
                'amount' => (int) $newEntry->montant_face_recoit,
            ];
        }, 3);

        /** @var Mission $lockedMission */
        $lockedMission = $result['mission'];
        /** @var Candidature $candidature */
        $candidature = $result['candidature'];
        $this->notifyReplacement($lockedMission, $candidature, $result['amount']);

        return $candidature;
    }

    /**
     * Rembourse une place libérée restée sans remplaçante une fois le jour de tournage commencé.
     * Idempotent (no-op si l'entry n'est plus libérée et en séquestre).
     */
    public function refundIfShootingDayStarted(MissionPaymentCandidature $entryRef): bool
    {
        if ($entryRef->mission_payment_id === null) {
            return false;
        }

        $result = DB::transaction(function () use ($entryRef): ?array {
            $this->missionPaymentService->lockCashSelection((int) $entryRef->mission_payment_id);

            /** @var MissionPayment|null $payment */
            $payment = MissionPayment::query()->find($entryRef->mission_payment_id);
            /** @var Mission|null $mission */
            $mission = $payment !== null ? Mission::query()->lockForUpdate()->find($payment->mission_id) : null;
            /** @var MissionPaymentCandidature|null $entry */
            $entry = MissionPaymentCandidature::query()->find($entryRef->id);

            if ($payment === null || $mission === null || $entry === null
                || $payment->status !== MissionPaymentStatus::Paid
                || $entry->freed_at === null
                || $entry->escrow_status !== EscrowStatus::Locked) {
                return null;
            }

            $shootingStart = $mission->shootingDayStartsAt();

            if ($shootingStart !== null && now()->lt($shootingStart)) {
                return null;
            }

            $amount = (int) $payment->budget_par_face;

            $this->missionPaymentService->refundToProducer(
                $entry,
                $mission,
                'freed_place_not_replaced',
                ['candidature_id' => $entry->candidature_id, 'refund_basis' => 'budget_par_face'],
                walletLabel: "Mission : place libérée non pourvue — {$mission->titre}",
                amountOverride: $amount,
            );

            if ($entry->fresh()?->escrow_status !== EscrowStatus::Refunded) {
                throw new \RuntimeException("Impossible de rembourser la place libérée (entry {$entry->id}).");
            }

            return [
                'mission' => $mission,
                'amount' => $amount,
                'no_face_left' => ! $this->missionPaymentService->hasActiveSelectedFace($payment),
                // Une entry issue d'un remplacement : une remplaçante avait été choisie mais n'a pas confirmé.
                'replacement_chosen' => $entry->replacement_of_entry_id !== null,
            ];
        }, 3);

        if ($result === null) {
            return false;
        }

        /** @var Mission $mission */
        $mission = $result['mission'];
        $formattedAmount = number_format($result['amount'], 0, ',', ' ');

        $this->notify(fn (int $userId) => Notification::create([
            'user_id' => $userId,
            'type' => 'mission_freed_place_refunded',
            'data' => [
                'message' => ($result['replacement_chosen'] ? "Aucune remplaçante n'a confirmé" : "Aucune remplaçante n'a été choisie à temps")
                    ." pour la place libérée sur « {$mission->titre} » : {$formattedAmount} FCFA ont été crédités sur votre wallet.",
                'mission_id' => $mission->id,
                'montant' => $result['amount'],
                'url' => "/producer/missions/{$mission->uuid}?tab=candidatures",
            ],
        ]), $this->producerUserId($mission));

        if ($result['no_face_left']) {
            $this->missionPaymentService->notifyAllPlacesRefunded($mission);
        }

        return true;
    }

    private function notifyReplacement(Mission $mission, Candidature $candidature, int $amount): void
    {
        $candidature->setRelation('mission', $mission);
        $deadline = $candidature->confirmationDeadline();
        $deadlineLabel = $deadline !== null
            ? Booking::formatForBusiness($deadline, 'd/m').' à '.Booking::formatForBusiness($deadline, 'H:i')
            : null;

        /** @var User|null $faceUser */
        $faceUser = User::query()
            ->where('userable_type', Face::class)
            ->where('userable_id', $candidature->face_id)
            ->with('userable')
            ->first();

        $this->notify(fn (int $userId) => Notification::create([
            'user_id' => $userId,
            'type' => 'mission_place_freed_selected',
            'data' => [
                'message' => "Une place s'est libérée sur la mission « {$mission->titre} » : vous êtes retenue."
                    .($deadlineLabel !== null ? " Confirmez votre participation avant le {$deadlineLabel}." : ' Confirmez votre participation.'),
                'mission_id' => $mission->id,
                'candidature_id' => $candidature->id,
                'url' => "/face/missions/{$mission->uuid}",
            ],
        ]), $faceUser?->id);

        $face = $faceUser?->userable;

        if (! $face instanceof Face || trim((string) $faceUser?->email) === '') {
            return;
        }

        try {
            $producer = $mission->producer;
            $producerName = $producer instanceof Producer && trim((string) $producer->display_name) !== ''
                ? trim((string) $producer->display_name)
                : 'Le Producteur';

            Mail::to((string) $faceUser?->email)->queue(new FaceSelectedMail(
                face: $face,
                mission: $mission,
                producerName: $producerName,
                amount: $amount,
                confirmationDeadline: $deadline !== null ? Booking::formatForBusiness($deadline, 'd/m/Y \à H\hi') : null,
            ));
        } catch (\Throwable $e) {
            Log::warning('FaceSelectedMail (freed place) queue failed', [
                'mission_id' => $mission->id,
                'candidature_id' => $candidature->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function producerUserId(Mission $mission): ?int
    {
        $id = User::query()
            ->where('userable_type', Producer::class)
            ->where('userable_id', $mission->producer_id)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Notification in-app non fatale (l'action est déjà validée).
     *
     * @param  \Closure(int): mixed  $create
     */
    private function notify(\Closure $create, ?int $userId): void
    {
        if ($userId === null) {
            return;
        }

        try {
            $create($userId);
        } catch (\Throwable $e) {
            Log::warning('Freed place notification failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
        }
    }
}
