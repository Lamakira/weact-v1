<?php

declare(strict_types=1);

namespace App\Services;

use App\Concerns\RecordsFinancialEvent;
use App\Enums\AttendanceStatus;
use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\FinancialEventType;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Exceptions\MissionPaymentInitiationException;
use App\Mail\FaceSelectedMail;
use App\Mail\MissionCompletedMail;
use App\Models\Candidature;
use App\Models\Conversation;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use App\ValueObjects\MissionPricing;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MissionPaymentService
{
    use RecordsFinancialEvent;

    private const MISSION_PAYMENT_LOCK_TTL_SECONDS = 120;

    public function __construct(
        private readonly FedapayService $fedapayService,
        private readonly WalletService $walletService,
    ) {}

    /**
     * Confirm face selection, initiate the external checkout, then finalize local state.
     *
     * @param  string[]  $candidatureUuids
     * @return array{payment: MissionPayment, checkout_url: ?string}
     *
     * @throws ValidationException
     */
    public function confirmAndInitiatePayment(Mission $mission, array $candidatureUuids): array
    {
        return $this->withMissionPaymentLock($mission, function () use ($mission, $candidatureUuids): array {
            $existingPayment = $this->resolveResumablePayment($mission);

            if ($existingPayment instanceof MissionPayment) {
                try {
                    return $this->initiatePayment($existingPayment);
                } catch (\Throwable $e) {
                    // Resume path normalizes every failure (including ValidationException
                    // from stale payment state) into a single MissionPaymentInitiationException
                    // envelope, so the SPA has one response shape to parse for "resume failed".
                    $this->handleResumeInitiationFailure($existingPayment, $e);
                }
            }

            $preparedPayment = $this->prepareSelectionForPayment($mission, $candidatureUuids);
            $remotePayment = null;

            try {
                $remotePayment = $this->requestHostedCheckout($preparedPayment);
                $payment = $this->finalizePreparedPayment($preparedPayment, $remotePayment);
            } catch (\Throwable $e) {
                $this->handleInitiationFailure($preparedPayment, $remotePayment, $e);
            }

            return [
                'payment' => $payment,
                'checkout_url' => $remotePayment['checkout_url'],
            ];
        });
    }

    /**
     * Prepare the mission payment row + escrow stubs before external checkout initiation.
     *
     * Candidature statuses are NOT mutated here (FIX-20.1). They remain `Pending`
     * until the FedaPay webhook confirms the payment, at which point
     * `applySelectionOutcomesOnPaid` transitions selected → `Accepted`,
     * non-selected → `Rejected`, and provisions each Conversation row.
     *
     * @param  string[]  $candidatureUuids
     *
     * @throws ValidationException
     */
    private function prepareSelectionForPayment(Mission $mission, array $candidatureUuids): MissionPayment
    {
        return DB::transaction(function () use ($mission, $candidatureUuids): MissionPayment {
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

            $pricing = new MissionPricing($mission->budget, $selectedCandidatures->count());

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

            foreach ($selectedCandidatures as $candidature) {
                MissionPaymentCandidature::create([
                    'mission_payment_id' => $payment->id,
                    'candidature_id' => $candidature->id,
                    'face_id' => $candidature->face_id,
                    'montant_face_recoit' => $pricing->montantParFace,
                    'escrow_status' => EscrowStatus::Pending,
                ]);
            }

            $mission->update(['status' => MissionStatus::PendingPayment]);

            /** @var MissionPayment $freshPayment */
            $freshPayment = $payment->fresh();

            return $freshPayment;
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
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     *
     * @throws ValidationException
     */
    private function withMissionPaymentLock(Mission $mission, callable $callback): mixed
    {
        $lock = Cache::lock("mission_payment_{$mission->id}", self::MISSION_PAYMENT_LOCK_TTL_SECONDS);

        try {
            return $lock->block(5, $callback);
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                'status' => ['Un paiement est deja en cours pour cette mission.'],
            ]);
        } finally {
            optional($lock)->release();
        }
    }

    private function resolveResumablePayment(Mission $mission): ?MissionPayment
    {
        /** @var Mission $freshMission */
        $freshMission = Mission::query()
            ->with('payment')
            ->findOrFail($mission->id);

        $payment = $freshMission->payment;

        if (
            ! $payment instanceof MissionPayment
            || $freshMission->status !== MissionStatus::PendingPayment
            || $payment->status !== MissionPaymentStatus::Pending
        ) {
            return null;
        }

        return $payment;
    }

    /**
     * @throws MissionPaymentInitiationException
     */
    private function handleResumeInitiationFailure(MissionPayment $payment, \Throwable $exception): never
    {
        Log::error('Mission payment resume failed', [
            'payment_id' => $payment->id,
            'mission_id' => $payment->mission_id,
            'producer_id' => $payment->producer_id,
            'phase' => 'resume',
            'remote_transaction_id' => $payment->fedapay_transaction_id,
            'error_class' => $exception::class,
            'error_message' => $exception->getMessage(),
        ]);

        throw new MissionPaymentInitiationException(
            'Le paiement de la mission n\'a pas pu être initialisé. Veuillez réessayer.',
            previous: $exception,
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
     * @param  array{fedapay_transaction_id: int, checkout_url: string}|null  $remotePayment
     *
     * @throws MissionPaymentInitiationException
     */
    private function handleInitiationFailure(MissionPayment $payment, ?array $remotePayment, \Throwable $exception): never
    {
        $compensationAttempted = false;
        $compensationFailed = null;
        $remoteTransactionId = $remotePayment['fedapay_transaction_id'] ?? null;
        $paymentId = $payment->id;
        $missionId = $payment->mission_id;
        $producerId = $payment->producer_id;
        $hasPersistedTransactionId = $this->hasPersistedTransactionId($paymentId);
        $needsCompensation = $remotePayment === null || ! $hasPersistedTransactionId;

        // Failure phase: encodes where in the confirm/initiate workflow the throw happened,
        // so operators can map the log line to the affected DB rows without reading the source.
        $phase = match (true) {
            $remotePayment === null => 'request_checkout',
            ! $hasPersistedTransactionId => 'finalize_local',
            default => 'post_finalize',
        };

        if ($needsCompensation) {
            $compensationAttempted = true;

            try {
                $this->compensateFailedPreparation($payment);
            } catch (\Throwable $compensationException) {
                $compensationFailed = $compensationException;
            }
        }

        $compensationOutcome = match (true) {
            ! $compensationAttempted => 'skipped',
            $compensationFailed !== null => 'failed',
            default => 'succeeded',
        };

        Log::error('Mission payment initiation failed', [
            'payment_id' => $paymentId,
            'mission_id' => $missionId,
            'producer_id' => $producerId,
            'phase' => $phase,
            'remote_transaction_id' => $remoteTransactionId,
            'needs_compensation' => $needsCompensation,
            'compensation_attempted' => $compensationAttempted,
            'compensation_failed' => $compensationFailed !== null,
            'compensation_outcome' => $compensationOutcome,
            'manual_recovery_required' => $remoteTransactionId !== null && $needsCompensation,
            'error_class' => $exception::class,
            'error_message' => $exception->getMessage(),
        ]);

        if ($compensationFailed instanceof \Throwable) {
            Log::error('Mission payment compensation failed after initiation error', [
                'payment_id' => $paymentId,
                'mission_id' => $missionId,
                'producer_id' => $producerId,
                'phase' => 'compensate',
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

    protected function compensateFailedPreparation(MissionPayment $preparedPayment): void
    {
        DB::transaction(function () use ($preparedPayment): void {
            /** @var MissionPayment|null $payment */
            $payment = MissionPayment::lockForUpdate()->find($preparedPayment->id);

            if (! $payment || $payment->fedapay_transaction_id !== null) {
                return;
            }

            /** @var Mission|null $mission */
            $mission = Mission::lockForUpdate()->find($payment->mission_id);

            if ($mission && $mission->status === MissionStatus::PendingPayment) {
                $mission->update(['status' => MissionStatus::Published]);
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
     * @return array{payment: MissionPayment, checkout_url: ?string}
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

                if ($existing->status === 'approved') {
                    // Self-healing: the FedaPay webhook never landed (or is delayed) but the
                    // remote side says the transaction is approved. We reconcile local state
                    // here, nested inside initiatePayment()'s DB::transaction and
                    // withMissionPaymentLock()'s cache lock.
                    //
                    // ⚠️ REQUIRES QUEUE_CONNECTION=database (or sync).
                    // markAsPaid() fans out N+1 Notification::create() calls which can
                    // dispatch model-observer jobs. With a non-DB queue driver (redis, sqs),
                    // those jobs could be consumed by a worker before the outer transaction
                    // commits, racing against the not-yet-visible rows. Switching the queue
                    // driver requires moving this self-healing path outside the cache lock
                    // (e.g., return a "needs_reconcile" flag and let the controller call
                    // markAsPaid() after the lock is released).
                    $paidPayment = $this->markAsPaid(
                        $payment,
                        (string) ($existing->reference ?? $payment->fedapay_transaction_id)
                    );

                    return ['payment' => $paidPayment, 'checkout_url' => null];
                }

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

            // Transition candidatures and provision conversations now that payment is confirmed.
            // Selected → Accepted, remaining pending → Rejected, Conversation::firstOrCreate per accepted.
            $this->applySelectionOutcomesOnPaid($payment);

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

            // Queue FaceSelectedMail for each selected face (non-fatal, best-effort).
            $mission->loadMissing('producer');
            $producer = $mission->producer;
            $producerDisplayName = $producer instanceof Producer ? trim((string) $producer->display_name) : '';
            $producerName = $producerDisplayName !== '' ? $producerDisplayName : 'Le Producteur';

            foreach ($payment->entries as $entry) {
                /** @var MissionPaymentCandidature $entry */
                $userId = $this->getUserIdForFace($entry->face_id);

                if ($userId === null) {
                    continue;
                }

                /** @var User|null $faceUser */
                $faceUser = User::query()->with('userable')->find($userId);

                if (! $faceUser || ! $faceUser->email) {
                    continue;
                }

                $face = $faceUser->userable instanceof Face ? $faceUser->userable : null;

                if (! $face) {
                    continue;
                }

                try {
                    Mail::to($faceUser->email)->queue(
                        new FaceSelectedMail(
                            face: $face,
                            mission: $mission,
                            producerName: $producerName,
                            amount: (int) $entry->montant_face_recoit,
                        )
                    );
                } catch (\Throwable $e) {
                    Log::warning('FaceSelectedMail queue failed', [
                        'mission_id' => $mission->id,
                        'payment_id' => $payment->id,
                        'face_id' => $entry->face_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            /** @var MissionPayment $freshPayment */
            $freshPayment = $payment->fresh();

            return $freshPayment;
        });
    }

    /**
     * Finalize the selection outcome once payment is confirmed:
     * - selected candidatures: Pending → Accepted (idempotent — skipped if already Accepted)
     * - remaining pending candidatures on the same mission: → Rejected
     * - Conversation row provisioned per newly-accepted candidature
     * - candidature_rejected notifications dispatched to each rejected Face
     *
     * Must run inside the same DB transaction as markAsPaid() so replays are
     * atomic with the payment status flip (idempotency comes from markAsPaid's
     * early return on Paid status + a status guard here).
     */
    protected function applySelectionOutcomesOnPaid(MissionPayment $payment): void
    {
        $selectedCandidatureIds = $payment->entries->pluck('candidature_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $newlyAcceptedIds = [];

        if ($selectedCandidatureIds !== []) {
            /** @var \Illuminate\Support\Collection<int, Candidature> $selectedPending */
            $selectedPending = Candidature::whereIn('id', $selectedCandidatureIds)
                ->where('status', CandidatureStatus::Pending->value)
                ->lockForUpdate()
                ->get();

            foreach ($selectedPending as $candidature) {
                /** @var Candidature $candidature */
                $candidature->update(['status' => CandidatureStatus::Accepted]);
                $newlyAcceptedIds[] = (int) $candidature->id;
            }
        }

        foreach ($newlyAcceptedIds as $candidatureId) {
            Conversation::firstOrCreate(['candidature_id' => $candidatureId]);
        }

        /** @var \Illuminate\Support\Collection<int, Candidature> $rejectedCandidatures */
        $rejectedCandidatures = Candidature::where('mission_id', $payment->mission_id)
            ->where('status', CandidatureStatus::Pending->value)
            ->whereNotIn('id', $selectedCandidatureIds)
            ->lockForUpdate()
            ->get();

        $mission = $payment->mission;
        $missionTitre = $mission instanceof Mission ? $mission->titre : '';

        $notifications = [];

        foreach ($rejectedCandidatures as $rejected) {
            /** @var Candidature $rejected */
            $rejected->update(['status' => CandidatureStatus::Rejected]);

            $notifications[] = [
                'userId' => $this->getUserIdForFace($rejected->face_id),
                'type' => 'candidature_rejected',
                'data' => [
                    'message' => 'Votre candidature n\'a pas été retenue.',
                    'mission_id' => $payment->mission_id,
                    'mission_titre' => $missionTitre,
                    'url' => '/face/candidatures',
                ],
            ];
        }

        $this->dispatchSelectionNotifications($notifications);
    }

    /**
     * Release escrowed funds for each Locked entry of a paid mission, routed by attendance:
     * - present  → release to Face wallet
     * - absent   → refund to Producer wallet (100 % of the per-Face amount)
     * - disputed → skip (admin will resolve later — FIX-26.8)
     * - pending  → invariant violation, RuntimeException
     *
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

            // FIX-26.2 — Skip disputed entries; admin will resolve them later (FIX-26.8).
            if ($entry->attendance_status === AttendanceStatus::Disputed) {
                continue;
            }

            // FIX-26.2 — Pending = presence not yet validated, invariant violation.
            // `continue` and `throw` cannot be expressions inside a `match` in PHP, so we
            // gate Disputed/Pending before the match and keep the match exhaustive on the
            // two routing cases. A future AttendanceStatus case (e.g. Late) will trip
            // UnhandledMatchError, which is the defensive behavior we want.
            if ($entry->attendance_status === AttendanceStatus::Pending) {
                throw new \RuntimeException(
                    "MissionPaymentService::releaseFunds — entry {$entry->id} has attendance_status=pending; presence must be validated before release."
                );
            }

            match ($entry->attendance_status) {
                AttendanceStatus::Present => $this->releaseToFace($entry, $mission),
                AttendanceStatus::Absent => $this->refundToProducer($entry, $mission),
            };
        }
    }

    /**
     * Release one entry to its Face: credit wallet, mark Released, complete candidature,
     * notify, queue MissionCompletedMail, and record the EscrowRelease FinancialEvent.
     *
     * MUST be called inside an existing DB::transaction().
     *
     * @param  array<string, mixed>  $extraMetadata
     */
    public function releaseToFace(
        MissionPaymentCandidature $entry,
        Mission $mission,
        string $reason = 'attendance_present',
        array $extraMetadata = [],
    ): void {
        $userId = $this->getUserIdForFace($entry->face_id);

        if ($userId === null) {
            Log::warning('MissionPaymentService::releaseToFace — face user not found', [
                'face_id' => $entry->face_id,
                'mission_id' => $mission->id,
            ]);

            return;
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

        $this->recordMissionAttendanceFinancialEvent(
            FinancialEventType::EscrowRelease,
            $entry,
            (int) $entry->montant_face_recoit,
            [
                'status' => 'completed',
                'metadata' => array_merge($extraMetadata, ['reason' => $reason]),
            ],
        );

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

        // FIX-24.4: best-effort MissionCompletedMail to the Face.
        // Non-fatal: NEVER rolls back the parent DB::transaction(completeMission).
        /** @var User|null $faceUser */
        $faceUser = User::query()->with('userable')->find($userId);

        if (! $faceUser || ! $faceUser->email) {
            return;
        }

        $face = $faceUser->userable instanceof Face ? $faceUser->userable : null;

        if (! $face) {
            return;
        }

        try {
            Mail::to($faceUser->email)->queue(
                new MissionCompletedMail(
                    face: $face,
                    mission: $mission,
                    amount: (int) $entry->montant_face_recoit,
                )
            );
        } catch (\Throwable $e) {
            Log::warning('MissionCompletedMail queue failed', [
                'mission_id' => $mission->id,
                'payment_id' => $entry->mission_payment_id,
                'face_id' => $entry->face_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Refund one entry to its Producer (100 % of the per-Face amount): credit Producer wallet,
     * mark Refunded, and record the Refund FinancialEvent. Candidature status is NOT touched
     * (an absent Face does not "complete" the candidature). No notification or email is sent
     * here — the Producer-facing notification is deferred to FIX-26.4/26.5.
     *
     * MUST be called inside an existing DB::transaction().
     *
     * @param  array<string, mixed>  $extraMetadata
     */
    public function refundToProducer(
        MissionPaymentCandidature $entry,
        Mission $mission,
        string $reason = 'attendance_absent',
        array $extraMetadata = [],
    ): void {
        $producerUserId = $this->getUserIdForProducer($mission->producer_id);

        if ($producerUserId === null) {
            Log::warning('MissionPaymentService::refundToProducer — producer user not found', [
                'producer_id' => $mission->producer_id,
                'mission_id' => $mission->id,
            ]);

            return;
        }

        $this->walletService->creditDirect(
            $producerUserId,
            (int) $entry->montant_face_recoit,
            "Mission : remboursement absence Face — {$mission->titre}",
        );

        $entry->update([
            'escrow_status' => EscrowStatus::Refunded,
            'refunded_at' => now(),
        ]);

        $this->recordMissionAttendanceFinancialEvent(
            FinancialEventType::Refund,
            $entry,
            (int) $entry->montant_face_recoit,
            [
                'status' => 'completed',
                'metadata' => array_merge(
                    $extraMetadata,
                    ['reason' => $reason, 'refund_percentage' => 100],
                ),
            ],
        );
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
     * Get the User ID for a given Producer profile ID.
     */
    private function getUserIdForProducer(int $producerId): ?int
    {
        /** @var User|null $user */
        $user = User::where('userable_type', Producer::class)
            ->where('userable_id', $producerId)
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
