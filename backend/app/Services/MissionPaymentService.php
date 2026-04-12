<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Exceptions\MissionPaymentInitiationException;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Notification;
use App\Models\User;
use App\ValueObjects\MissionPricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MissionPaymentService
{
    public function __construct(
        private readonly FedapayService $fedapayService,
        private readonly WalletService $walletService,
    ) {}

    /**
     * Confirm face selection, initiate the external checkout, then finalize local state.
     *
     * @param  string[]  $candidatureUuids
     * @return array{payment: MissionPayment, checkout_url: string}
     *
     * @throws ValidationException
     */
    public function confirmAndInitiatePayment(Mission $mission, array $candidatureUuids): array
    {
        $prepared = $this->prepareSelectionForPayment($mission, $candidatureUuids);
        $remotePayment = null;

        try {
            $remotePayment = $this->requestHostedCheckout($prepared['payment']);
            $payment = $this->finalizePreparedPayment($prepared['payment'], $remotePayment);
        } catch (\Throwable $e) {
            $this->handleInitiationFailure($prepared, $remotePayment, $e);
        }

        $this->dispatchSelectionNotifications($prepared['notifications']);

        return [
            'payment' => $payment,
            'checkout_url' => $remotePayment['checkout_url'],
        ];
    }

    /**
     * Tentatively apply the mission selection changes before external payment initiation.
     *
     * @param  string[]  $candidatureUuids
     * @return array{
     *   payment: MissionPayment,
     *   selected_candidature_ids: list<int>,
     *   rejected_candidature_ids: list<int>,
     *   notifications: list<array{userId: ?int, type: string, data: array<string, mixed>}>
     * }
     *
     * @throws ValidationException
     */
    private function prepareSelectionForPayment(Mission $mission, array $candidatureUuids): array
    {
        return DB::transaction(function () use ($mission, $candidatureUuids): array {
            // Lock mission row
            /** @var Mission $mission */
            $mission = Mission::lockForUpdate()->findOrFail($mission->id);

            if ($mission->status !== MissionStatus::Published) {
                throw ValidationException::withMessages([
                    'mission' => ['Cette mission ne peut pas recevoir de sélection dans son état actuel.'],
                ]);
            }

            $requestedUuids = array_values(array_unique($candidatureUuids));

            $candidatures = Candidature::whereIn('uuid', $requestedUuids)
                ->where('mission_id', $mission->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('uuid');

            $invalidUuids = [];

            foreach ($requestedUuids as $candidatureUuid) {
                /** @var Candidature|null $candidature */
                $candidature = $candidatures->get($candidatureUuid);

                if (! $candidature || $candidature->status !== CandidatureStatus::Pending) {
                    $invalidUuids[] = $candidatureUuid;
                }
            }

            if ($invalidUuids !== []) {
                throw ValidationException::withMessages([
                    'candidature_ids' => [
                        'Certaines candidatures sont invalides ou ne sont plus en attente pour cette mission.',
                    ],
                ]);
            }

            /** @var \Illuminate\Support\Collection<int, Candidature> $selectedCandidatures */
            $selectedCandidatures = collect($requestedUuids)
                ->map(fn (string $candidatureUuid): Candidature => $candidatures->get($candidatureUuid));
            $selectedCandidatureIds = $selectedCandidatures->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

            $pricing = new MissionPricing($mission->budget, $selectedCandidatures->count());
            $notifications = [];

            // Create MissionPayment
            /** @var MissionPayment $payment */
            $payment = MissionPayment::create([
                'mission_id' => $mission->id,
                'producer_id' => $mission->producer_id,
                'nombre_faces_retenues' => $selectedCandidatures->count(),
                'budget_par_face' => $pricing->budgetParFace,
                'montant_sous_total' => $pricing->sousTotal,
                'commission_producteur' => $pricing->commissionProducteur,
                'montant_total_producteur' => $pricing->montantTotalProducteur,
                'commission_faces_total' => $pricing->commissionFacesTotal,
                'montant_total_faces' => $pricing->montantTotalFaces,
                'status' => MissionPaymentStatus::Pending,
            ]);

            // Create MissionPaymentCandidature entries and accept selected candidatures
            foreach ($selectedCandidatures as $candidature) {
                MissionPaymentCandidature::create([
                    'mission_payment_id' => $payment->id,
                    'candidature_id' => $candidature->id,
                    'face_id' => $candidature->face_id,
                    'montant_face_recoit' => $pricing->montantParFace,
                    'escrow_status' => EscrowStatus::Pending,
                ]);

                $candidature->update(['status' => CandidatureStatus::Accepted]);

                $notifications[] = [
                    'userId' => $this->getUserIdForFace($candidature->face_id),
                    'type' => 'candidature_accepted',
                    'data' => [
                        'message' => 'Votre candidature a été acceptée !',
                        'mission_id' => $mission->id,
                        'mission_titre' => $mission->titre,
                        'url' => '/face/candidatures',
                    ],
                ];
            }

            // Reject all remaining pending candidatures for this mission
            $rejectedCandidatures = Candidature::where('mission_id', $mission->id)
                ->where('status', CandidatureStatus::Pending->value)
                ->lockForUpdate()
                ->get();
            $rejectedCandidatureIds = [];

            foreach ($rejectedCandidatures as $rejected) {
                /** @var Candidature $rejected */
                $rejected->update(['status' => CandidatureStatus::Rejected]);
                $rejectedCandidatureIds[] = $rejected->id;

                $notifications[] = [
                    'userId' => $this->getUserIdForFace($rejected->face_id),
                    'type' => 'candidature_rejected',
                    'data' => [
                        'message' => 'Votre candidature n\'a pas été retenue.',
                        'mission_id' => $mission->id,
                        'mission_titre' => $mission->titre,
                        'url' => '/face/candidatures',
                    ],
                ];
            }

            // Update mission status
            $mission->update(['status' => MissionStatus::PendingPayment]);

            return [
                'payment' => $payment->fresh(),
                'selected_candidature_ids' => $selectedCandidatureIds,
                'rejected_candidature_ids' => $rejectedCandidatureIds,
                'notifications' => $notifications,
            ];
        });
    }

    /**
     * @return array{fedapay_transaction_id: int, checkout_url: string}
     */
    protected function requestHostedCheckout(MissionPayment $payment): array
    {
        return $this->fedapayService->initiatePaymentForMission(
            $payment,
            Str::uuid()->toString(),
        );
    }

    /**
     * @param  array{fedapay_transaction_id: int, checkout_url: string}  $remotePayment
     */
    protected function finalizePreparedPayment(MissionPayment $payment, array $remotePayment): MissionPayment
    {
        return DB::transaction(function () use ($payment, $remotePayment): MissionPayment {
            /** @var MissionPayment $lockedPayment */
            $lockedPayment = MissionPayment::lockForUpdate()->findOrFail($payment->id);

            if ($lockedPayment->status !== MissionPaymentStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => ['Ce paiement ne peut pas être initié dans son état actuel.'],
                ]);
            }

            $lockedPayment->update([
                'fedapay_transaction_id' => $remotePayment['fedapay_transaction_id'],
            ]);

            /** @var MissionPayment $freshPayment */
            $freshPayment = $lockedPayment->fresh();

            return $freshPayment;
        });
    }

    /**
     * @param  array{
     *   payment: MissionPayment,
     *   selected_candidature_ids: list<int>,
     *   rejected_candidature_ids: list<int>,
     *   notifications: list<array{userId: ?int, type: string, data: array<string, mixed>}>
     * }  $prepared
     * @param  array{fedapay_transaction_id: int, checkout_url: string}|null  $remotePayment
     *
     * @throws MissionPaymentInitiationException
     */
    private function handleInitiationFailure(array $prepared, ?array $remotePayment, \Throwable $exception): never
    {
        $compensationAttempted = false;
        $compensationFailed = null;
        $remoteTransactionId = $remotePayment['fedapay_transaction_id'] ?? null;
        $paymentId = $prepared['payment']->id;
        $needsCompensation = $remotePayment === null || ! $this->hasPersistedTransactionId($paymentId);

        if ($needsCompensation) {
            $compensationAttempted = true;

            try {
                $this->compensateFailedPreparation($prepared);
            } catch (\Throwable $compensationException) {
                $compensationFailed = $compensationException;
            }
        }

        Log::error('Mission payment initiation failed', [
            'mission_payment_id' => $paymentId,
            'mission_id' => $prepared['payment']->mission_id,
            'remote_transaction_id' => $remoteTransactionId,
            'needs_compensation' => $needsCompensation,
            'compensation_attempted' => $compensationAttempted,
            'compensation_failed' => $compensationFailed !== null,
            'manual_recovery_required' => $remoteTransactionId !== null && $needsCompensation,
            'error_class' => $exception::class,
            'error_message' => $exception->getMessage(),
        ]);

        if ($compensationFailed instanceof \Throwable) {
            Log::error('Mission payment compensation failed after initiation error', [
                'mission_payment_id' => $paymentId,
                'mission_id' => $prepared['payment']->mission_id,
                'remote_transaction_id' => $remoteTransactionId,
                'original_error_class' => $exception::class,
                'original_error_message' => $exception->getMessage(),
                'compensation_error_class' => $compensationFailed::class,
                'compensation_error_message' => $compensationFailed->getMessage(),
            ]);
        }

        throw new MissionPaymentInitiationException(
            'Le paiement de la mission n\'a pas pu être initialisé. Veuillez réessayer.',
            previous: $exception,
        );
    }

    private function hasPersistedTransactionId(int $paymentId): bool
    {
        return MissionPayment::query()
            ->whereKey($paymentId)
            ->whereNotNull('fedapay_transaction_id')
            ->exists();
    }

    /**
     * @param  array{
     *   payment: MissionPayment,
     *   selected_candidature_ids: list<int>,
     *   rejected_candidature_ids: list<int>,
     *   notifications: list<array{userId: ?int, type: string, data: array<string, mixed>}>
     * }  $prepared
     */
    protected function compensateFailedPreparation(array $prepared): void
    {
        DB::transaction(function () use ($prepared): void {
            /** @var MissionPayment|null $payment */
            $payment = MissionPayment::lockForUpdate()->find($prepared['payment']->id);

            if (! $payment || $payment->fedapay_transaction_id !== null) {
                return;
            }

            /** @var Mission|null $mission */
            $mission = Mission::lockForUpdate()->find($payment->mission_id);

            if ($mission && $mission->status === MissionStatus::PendingPayment) {
                $mission->update(['status' => MissionStatus::Published]);
            }

            if ($prepared['selected_candidature_ids'] !== []) {
                Candidature::whereIn('id', $prepared['selected_candidature_ids'])
                    ->where('status', CandidatureStatus::Accepted->value)
                    ->update(['status' => CandidatureStatus::Pending->value]);
            }

            if ($prepared['rejected_candidature_ids'] !== []) {
                Candidature::whereIn('id', $prepared['rejected_candidature_ids'])
                    ->where('status', CandidatureStatus::Rejected->value)
                    ->update(['status' => CandidatureStatus::Pending->value]);
            }

            $payment->delete();
        });
    }

    /**
     * @param  list<array{userId: ?int, type: string, data: array<string, mixed>}>  $notifications
     */
    private function dispatchSelectionNotifications(array $notifications): void
    {
        foreach ($notifications as $notification) {
            $this->notifySafely(
                userId: $notification['userId'],
                type: $notification['type'],
                data: $notification['data'],
            );
        }
    }

    /**
     * Initiate FedaPay checkout for a mission payment.
     * Idempotent: regenerates URL if transaction exists and is not failed.
     *
     * @return array{payment: MissionPayment, checkout_url: string}
     *
     * @throws ValidationException
     */
    public function initiatePayment(MissionPayment $payment): array
    {
        return DB::transaction(function () use ($payment): array {
            /** @var MissionPayment $payment */
            $payment = MissionPayment::lockForUpdate()->findOrFail($payment->id);

            // Idempotent: if transaction exists and not in failed state, regenerate URL
            if ($payment->fedapay_transaction_id !== null) {
                $existing = $this->fedapayService->retrieveTransaction((int) $payment->fedapay_transaction_id);
                $terminalFailedStatuses = ['declined', 'canceled', 'refunded'];

                if (! in_array($existing->status, $terminalFailedStatuses, true)) {
                    /** @var object{url:string} $tokenObj */
                    $tokenObj = $existing->generateToken();

                    return ['payment' => $payment, 'checkout_url' => $tokenObj->url];
                }

                $payment->update(['fedapay_transaction_id' => null, 'status' => MissionPaymentStatus::Pending]);
            }

            if ($payment->status !== MissionPaymentStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => ['Ce paiement ne peut pas être initié dans son état actuel.'],
                ]);
            }

            $idempotencyKey = Str::uuid()->toString();
            $result = $this->fedapayService->initiatePaymentForMission($payment, $idempotencyKey);

            $payment->update(['fedapay_transaction_id' => $result['fedapay_transaction_id']]);

            /** @var MissionPayment $freshPayment */
            $freshPayment = $payment->fresh();

            return ['payment' => $freshPayment, 'checkout_url' => $result['checkout_url']];
        });
    }

    /**
     * Mark a mission payment as paid after successful webhook.
     * Idempotent: skips if already paid.
     */
    public function markAsPaid(MissionPayment $payment, string $fedapayRef): MissionPayment
    {
        return DB::transaction(function () use ($payment, $fedapayRef): MissionPayment {
            /** @var MissionPayment $payment */
            $payment = MissionPayment::lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === MissionPaymentStatus::Paid) {
                return $payment;
            }

            $payment->update([
                'status' => MissionPaymentStatus::Paid,
                'paid_at' => now(),
                'fedapay_ref' => $fedapayRef,
            ]);

            // Lock all escrow entries
            $payment->entries()->update([
                'escrow_status' => EscrowStatus::Locked,
                'locked_at' => now(),
            ]);

            // Update mission to closed
            $mission = $payment->mission;
            if (! $mission instanceof Mission) {
                throw new \RuntimeException('Mission introuvable pour ce paiement.');
            }

            $mission->update(['status' => MissionStatus::Closed]);

            // Leave accepted candidatures as-is — each Face must confirm their participation
            // before the candidature moves to in_progress (via Face\CandidatureController::confirm)

            // Notify producer
            /** @var User|null $producerUser */
            $producerUser = User::where('userable_type', \App\Models\Producer::class)
                ->where('userable_id', $payment->producer_id)
                ->first();

            if ($producerUser) {
                $this->notifySafely(
                    userId: $producerUser->id,
                    type: 'mission_payment_confirmed',
                    data: [
                        'message' => 'Paiement confirmé pour la mission '.$mission->titre,
                        'mission_id' => $mission->id,
                        'url' => "/producer/missions/{$mission->uuid}/candidatures",
                    ]
                );
            }

            // Notify each selected face — ask them to confirm their participation
            foreach ($payment->entries as $entry) {
                /** @var MissionPaymentCandidature $entry */
                $this->notifySafely(
                    userId: $this->getUserIdForFace($entry->face_id),
                    type: 'mission_participation_confirmation_required',
                    data: [
                        'message' => 'Vous avez été sélectionné(e) pour la mission "'.$mission->titre.'". Confirmez votre participation.',
                        'mission_id' => $mission->id,
                        'url' => '/face/candidatures',
                    ]
                );
            }

            /** @var MissionPayment $freshPayment */
            $freshPayment = $payment->fresh();

            return $freshPayment;
        });
    }

    /**
     * Release escrowed funds to each selected face after mission completion.
     * MUST be called inside an existing DB::transaction().
     */
    public function releaseFunds(Mission $mission): void
    {
        /** @var MissionPayment|null $payment */
        $payment = $mission->payment;

        if (! $payment || $payment->status !== MissionPaymentStatus::Paid) {
            return;
        }

        $entries = $payment->entries()
            ->where('escrow_status', EscrowStatus::Locked)
            ->with(['face', 'candidature'])
            ->get();

        foreach ($entries as $entry) {
            /** @var MissionPaymentCandidature $entry */
            if (
                ! $entry->candidature
                || ! in_array($entry->candidature->status, [
                    CandidatureStatus::Confirmed,
                    CandidatureStatus::InProgress,
                ], true)
            ) {
                throw new \RuntimeException(
                    "Mission {$mission->id} cannot release funds for candidature {$entry->candidature_id} without confirmed participation."
                );
            }

            $userId = $this->getUserIdForFace($entry->face_id);

            if ($userId === null) {
                Log::warning('MissionPaymentService::releaseFunds — face user not found', [
                    'face_id' => $entry->face_id,
                    'mission_id' => $mission->id,
                ]);

                continue;
            }

            $this->walletService->creditDirect(
                $userId,
                $entry->montant_face_recoit,
                "Mission : {$mission->titre}"
            );

            $entry->update([
                'escrow_status' => EscrowStatus::Released,
                'released_at' => now(),
            ]);

            // Move candidature to completed using candidature_id directly
            Candidature::where('id', $entry->candidature_id)
                ->whereIn('status', [CandidatureStatus::InProgress->value, CandidatureStatus::Confirmed->value])
                ->update(['status' => CandidatureStatus::Completed->value]);

            // Notify face: wallet credited + mission completed
            $this->notifySafely(
                userId: $userId,
                type: 'mission_completed',
                data: [
                    'message' => "La mission \"{$mission->titre}\" est terminée. Votre portefeuille a été crédité de {$entry->montant_face_recoit} XOF.",
                    'mission_id' => $mission->id,
                    'amount' => $entry->montant_face_recoit,
                    'url' => '/face/candidatures',
                ]
            );
        }
    }

    /**
     * Check if the mission has a paid payment (guard for progression).
     */
    public function hasPaidPayment(Mission $mission): bool
    {
        return MissionPayment::where('mission_id', $mission->id)
            ->where('status', MissionPaymentStatus::Paid)
            ->exists();
    }

    public function hasUnconfirmedSelectedFaces(Mission $mission): bool
    {
        /** @var MissionPayment|null $payment */
        $payment = $mission->payment;

        if (! $payment) {
            return false;
        }

        $selectedCandidatureIds = $payment->entries()->pluck('candidature_id');

        if ($selectedCandidatureIds->isEmpty()) {
            return false;
        }

        return Candidature::whereIn('id', $selectedCandidatureIds)
            ->where('status', CandidatureStatus::Accepted->value)
            ->exists();
    }

    /**
     * Get the User ID for a given Face profile ID.
     */
    private function getUserIdForFace(int $faceId): ?int
    {
        /** @var User|null $user */
        $user = User::where('userable_type', Face::class)
            ->where('userable_id', $faceId)
            ->first();

        return $user?->id;
    }

    /**
     * Create a notification silently (non-fatal on failure).
     *
     * @param  array<string, mixed>  $data
     */
    private function notifySafely(?int $userId, string $type, array $data): void
    {
        if ($userId === null) {
            return;
        }

        try {
            Notification::create([
                'user_id' => $userId,
                'type' => $type,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            Log::warning('MissionPaymentService: notification failed', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
