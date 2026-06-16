<?php

declare(strict_types=1);

namespace App\Services;

use App\Concerns\RecordsFinancialEvent;
use App\Enums\BookingCancellationReason;
use App\Enums\BookingStatus;
use App\Enums\CompensationType;
use App\Enums\FinancialEventType;
use App\Enums\UgcRefundReason;
use App\Events\BookingAccepted;
use App\Events\BookingCancelled;
use App\Events\BookingCompleted;
use App\Events\BookingCreated;
use App\Events\BookingExpired;
use App\Events\BookingNoShowReported;
use App\Events\BookingPaid;
use App\Events\BookingPartiallyConfirmed;
use App\Events\BookingRefused;
use App\Models\Booking;
use App\Models\Face;
use App\Models\User;
use App\Services\Ugc\UgcCommissionService;
use App\Services\Ugc\UgcRefundService;
use App\ValueObjects\BookingPricing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    use RecordsFinancialEvent;

    public function __construct(
        private readonly FedapayService $fedapayService,
        private readonly EscrowService $escrowService,
        private readonly WalletService $walletService,
        private readonly FaceEntitlementService $faceEntitlementService,
        private readonly UgcCommissionService $ugcCommissionService,
        private readonly UgcRefundService $ugcRefundService,
    ) {}

    /**
     * Create a new booking request.
     *
     * @param  array<string, mixed>  $data  Validated request data
     * @param  User  $producer  The authenticated Producer user
     * @return Booking The newly created booking
     *
     * @throws ValidationException
     */
    public function create(array $data, User $producer): Booking
    {
        $face = Face::where('uuid', $data['face_id'])->firstOrFail();
        $faceUser = User::where('userable_type', Face::class)
            ->where('userable_id', $face->id)
            ->firstOrFail();

        // FR11: Validate Face availability
        if (! $face->is_available) {
            throw ValidationException::withMessages([
                'face_id' => ['Cette Face n\'est pas disponible pour le moment.'],
            ]);
        }

        // FR10: Validate minimum 4h duration (cash bookings only — a UGC dotation has no shoot duration).
        if (($data['type_contenu'] ?? null) !== 'UGC' && ($data['duree_heures'] ?? 0) < 4) {
            throw ValidationException::withMessages([
                'duree_heures' => ['La duree minimale est de 4 heures.'],
            ]);
        }

        // UGC (story 1.1 ; RH.1): compensation produit/hybride — commission sur la valeur produit
        // (produit seul) ou sur le cash au palier Face (hybride), pas de tarif horaire ni de garde
        // tarif. Le chemin cash reste strictement inchangé.
        $booking = ($data['type_contenu'] ?? null) === 'UGC'
            ? $this->createUgcBooking($data, $producer, $faceUser, $face)
            : $this->createCashBooking($data, $producer, $faceUser, $face);

        BookingCreated::dispatch($booking);

        return $booking;
    }

    /**
     * Cash booking path (existing behavior, unchanged) — server-side pricing via BookingPricing VO.
     *
     * @param  array<string, mixed>  $data
     */
    private function createCashBooking(array $data, User $producer, User $faceUser, Face $face): Booking
    {
        // FR4: Calculate pricing via BookingPricing VO (server-side recalculation)
        $tarifBase = $this->calculateTarifBase($face, (int) $data['duree_heures']);

        if ($tarifBase === 0) {
            throw ValidationException::withMessages([
                'face_id' => ['Cette Face n\'a pas encore défini ses tarifs.'],
            ]);
        }

        // FP-3.1a: Face-side commission is tier-driven (Découverte 15 % / Starter-Pro 10 % / Élite 5 %),
        // locked in at booking creation. Producer side stays a flat 10 % (BookingPricing constant).
        $faceCommissionRate = $this->faceEntitlementService->capabilities($face)->commissionRate;
        $pricing = new BookingPricing($tarifBase, $faceCommissionRate);

        return Booking::create([
            'face_id' => $faceUser->id,
            'producer_id' => $producer->id,
            'status' => BookingStatus::Pending,
            'date_debut' => $data['date_debut'],
            'date_fin' => $data['date_fin'],
            'duree_heures' => $data['duree_heures'],
            'type_contenu' => $data['type_contenu'],
            'lieu' => ! empty($data['lieu']) ? $data['lieu'] : null,
            'message' => $data['message'] ?? null,
            'tarif_base' => $pricing->baseTarif,
            'montant_total_producteur' => $pricing->totalProducerPays,
            'montant_face_recoit' => $pricing->faceReceives,
        ]);
    }

    /**
     * UGC booking path (story 1.1, D-1.1.b): bypass de la garde tarif.
     * tarif_base=0 ; commission produit seul = valeur produit (compute) ;
     * commission hybride = cash au palier Face (computeHybrid, RH.1) ;
     * montant_face_recoit=montant_remuneration (0 si produit seul) ;
     * montant_total_producteur=commission_ugc + montant_remuneration.
     *
     * @param  array<string, mixed>  $data
     */
    private function createUgcBooking(array $data, User $producer, User $faceUser, Face $face): Booking
    {
        $compensation = $data['type_compensation'];
        $valeurProduit = (int) $data['valeur_produit'];

        $isHybrid = $compensation === CompensationType::Hybrid->value;
        $nombreVideos = $isHybrid ? (int) $data['nombre_videos'] : (int) config('ugc.product_only_video_count');
        $montantRemuneration = $isHybrid ? (int) $data['montant_remuneration'] : 0;

        // RH.1 : hybride → commission sur le CASH au palier d'abonnement de la Face (15/10/5 %),
        // jamais sur la valeur produit ; produit seul → commission produit inchangée (compute).
        $commission = $isHybrid
            ? $this->ugcCommissionService->computeHybrid(
                $montantRemuneration,
                $this->faceEntitlementService->capabilities($face)->commissionRate,
            )
            : $this->ugcCommissionService->compute($valeurProduit);

        return Booking::create([
            'face_id' => $faceUser->id,
            'producer_id' => $producer->id,
            'status' => BookingStatus::Pending,
            // UGC dotations have no shoot date/duration/location — never persist them, even
            // if a client sends them (the form omits them; this enforces the invariant server-side).
            'date_debut' => null,
            'date_fin' => null,
            'duree_heures' => null,
            'type_contenu' => 'UGC',
            'lieu' => null,
            'message' => $data['message'] ?? null,
            'tarif_base' => 0,                                    // D-1.1.b : pas de tarif horaire
            'montant_face_recoit' => $montantRemuneration,        // 0 si produit seul
            'montant_total_producteur' => $commission + $montantRemuneration,
            'type_compensation' => $compensation,
            'nom_produit' => $data['nom_produit'],
            'valeur_produit' => $valeurProduit,
            'nombre_videos' => $nombreVideos,
            'montant_remuneration' => $isHybrid ? $montantRemuneration : null,
            'commission_ugc' => $commission,
        ]);
    }

    /**
     * Accept a pending booking request.
     *
     * @throws ValidationException
     */
    public function accept(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $booking = $booking->lockForUpdate()->find($booking->id);

            // UGC (2.4) : l'acceptation n'ouvre qu'une fois la commission réglée.
            $expectedStatus = $booking->type_contenu === 'UGC'
                ? BookingStatus::CommissionPaid
                : BookingStatus::Pending;

            // Re-check sous lock du refund hors-procédure (AC8a/D-2.5.h) : la policy
            // est évaluée AVANT le lock — un webhook transaction.refunded settled
            // entre les deux laisserait accepter un deal remboursé.
            $refundedUgc = $booking->type_contenu === 'UGC' && $booking->commission_refunded_at !== null;

            if ($booking->status !== $expectedStatus || $refundedUgc) {
                throw ValidationException::withMessages([
                    'status' => ['Ce booking ne peut pas être accepté dans son état actuel.'],
                ]);
            }

            $booking->update([
                'status' => BookingStatus::Accepted,
                'accepted_at' => now(),
                'payment_reminder_sent_at' => null,
            ]);

            BookingAccepted::dispatch($booking);

            return $booking->fresh();
        });
    }

    /**
     * Refuse a booking request.
     *
     * @throws ValidationException
     */
    public function refuse(Booking $booking, ?string $reason = null): Booking
    {
        $wasCommissionPaid = false;

        $refused = DB::transaction(function () use ($booking, $reason, &$wasCommissionPaid) {
            $booking = $booking->lockForUpdate()->find($booking->id);

            // UGC (2.4) : refus possible avant paiement et après (refund = story 2.5).
            $refusableStatuses = $booking->type_contenu === 'UGC'
                ? [BookingStatus::Pending, BookingStatus::CommissionPaid]
                : [BookingStatus::Pending];

            if (! in_array($booking->status, $refusableStatuses, true)) {
                throw ValidationException::withMessages([
                    'status' => ['Ce booking ne peut pas être refusé dans son état actuel.'],
                ]);
            }

            $wasCommissionPaid = $booking->status === BookingStatus::CommissionPaid;

            $booking->update([
                'status' => BookingStatus::Refused,
                'cancellation_reason' => $reason,
            ]);

            BookingRefused::dispatch($booking);

            return $booking->fresh();
        });

        // UGC (2.6) : un refus APRÈS encaissement règle le remboursement (crédit wallet
        // synchrone, post-commit, no-throw — l'API refuse a déjà réussi). Refus à pending : rien.
        if ($wasCommissionPaid && $refused->type_contenu === 'UGC') {
            $this->ugcRefundService->settleRefundForBooking($refused, UgcRefundReason::Refused);
        }

        return $refused;
    }

    /**
     * Cancel a booking (Producer only).
     * - pending/accepted: immediate cancel with no financial operation
     * - paid: credit 90% to the Producer wallet
     *
     * @throws ValidationException
     */
    public function cancel(Booking $booking, string $reason, ?string $customReason = null): Booking
    {
        $customReason = trim((string) $customReason) ?: null;

        $cancelledBooking = DB::transaction(function () use ($booking, $reason, $customReason) {
            $booking = $booking->lockForUpdate()->find($booking->id);

            $cancellableStatuses = [
                BookingStatus::Pending,
                BookingStatus::Accepted,
                BookingStatus::Paid,
            ];

            if (! in_array($booking->status, $cancellableStatuses, true)) {
                throw ValidationException::withMessages([
                    'status' => ['Ce booking ne peut pas être annulé dans son état actuel.'],
                ]);
            }

            if ($booking->status === BookingStatus::Paid) {
                $this->escrowService->refund($booking, $this->walletService);
            }

            $booking->update([
                'status' => BookingStatus::CancelledByProducer,
                'cancellation_reason' => BookingCancellationReason::from($reason)->value,
                'custom_cancellation_reason' => $reason === BookingCancellationReason::Other->value
                    ? $customReason
                    : null,
            ]);

            return $booking->fresh();
        });

        try {
            BookingCancelled::dispatch($cancelledBooking);
        } catch (\Throwable $e) {
            Log::warning('BookingCancelled broadcast failed', [
                'booking_id' => $cancelledBooking->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $cancelledBooking;
    }

    /**
     * Cancel a booking as Face.
     * - accepted: immediate cancel with no financial operation
     * - paid: credit 90% to the Producer wallet
     * - after cancellation, apply face rating penalty (+1.0)
     *
     * @throws ValidationException
     */
    public function cancelByFace(Booking $booking, string $reason, ?string $customReason = null): Booking
    {
        $customReason = trim((string) $customReason) ?: null;

        $cancelledBooking = DB::transaction(function () use ($booking, $reason, $customReason) {
            $booking = $booking->lockForUpdate()->find($booking->id);

            $cancellableStatuses = [
                BookingStatus::Accepted,
                BookingStatus::Paid,
            ];

            if (! in_array($booking->status, $cancellableStatuses, true)) {
                throw ValidationException::withMessages([
                    'status' => ['Ce booking ne peut pas être annulé dans son état actuel.'],
                ]);
            }

            if ($booking->status === BookingStatus::Paid) {
                $this->escrowService->refund($booking, $this->walletService);
            }

            $booking->update([
                'status' => BookingStatus::CancelledByFace,
                'cancellation_reason' => BookingCancellationReason::from($reason)->value,
                'custom_cancellation_reason' => $reason === BookingCancellationReason::Other->value
                    ? $customReason
                    : null,
            ]);

            return $booking->fresh();
        });

        // Dispatch after transaction commits. A broadcast failure is non-fatal:
        // the booking status is already persisted in DB.
        try {
            BookingCancelled::dispatch($cancelledBooking);
        } catch (\Throwable $e) {
            Log::warning('BookingCancelled broadcast failed', [
                'booking_id' => $cancelledBooking->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $cancelledBooking->loadMissing('face.userable');

            $faceProfile = $cancelledBooking->face?->userable;
            if ($faceProfile instanceof Face) {
                $faceProfile->increment('rating_penalty', 1.0);
            }
        } catch (\Throwable $exception) {
            Log::warning('Failed to apply face rating penalty after cancellation', [
                'booking_id' => $cancelledBooking->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return $cancelledBooking;
    }

    /**
     * Report a Face no-show on a paid booking whose shooting date has passed.
     * Credits 100% of montant_total_producteur to Producer's wallet.
     * Updates escrow to refunded. Applies rating penalty to Face.
     *
     * @throws ValidationException
     */
    public function reportNoShow(Booking $booking, User $reporter): Booking
    {
        $reportedBooking = DB::transaction(function () use ($booking, $reporter): Booking {
            $lockedBooking = Booking::query()
                ->with(['face.userable', 'escrowTransaction'])
                ->lockForUpdate()
                ->findOrFail($booking->id);

            if ($lockedBooking->status !== BookingStatus::Paid) {
                throw ValidationException::withMessages([
                    'status' => ['Le signalement d\'absence n\'est possible que sur un booking payé.'],
                ]);
            }

            if ($lockedBooking->date_debut?->isFuture() ?? false) {
                throw ValidationException::withMessages([
                    'date_debut' => ['Le signalement d\'absence n\'est possible qu\'après la date de tournage.'],
                ]);
            }

            if ($reporter->id !== $lockedBooking->producer_id) {
                throw ValidationException::withMessages([
                    'reporter' => ['Seul le producteur peut signaler une absence.'],
                ]);
            }

            $lockedBooking->update(['status' => BookingStatus::NoShow]);

            // Mark escrow as refunded (no FedaPay — wallet credit instead)
            $this->escrowService->markRefundedForNoShow($lockedBooking);

            // Credit Producer wallet with 100% of montant_total_producteur
            $this->walletService->credit(
                $lockedBooking->producer_id,
                $lockedBooking->montant_total_producteur,
                $lockedBooking,
                'Booking : remboursement absence Face (100%)',
            );

            // Record financial event for audit trail
            $this->recordFinancialEvent(
                FinancialEventType::Refund,
                $lockedBooking,
                $lockedBooking->montant_total_producteur,
                ['status' => 'completed', 'metadata' => ['reason' => 'no_show', 'refund_percentage' => 100]],
            );

            return $lockedBooking->fresh();
        });

        // Apply rating penalty (non-fatal, outside transaction)
        try {
            $faceProfile = $reportedBooking->face?->userable;
            if ($faceProfile instanceof Face) {
                $faceProfile->increment('rating_penalty', 1.0);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to apply face rating penalty after no-show report', [
                'booking_id' => $reportedBooking->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Dispatch event (non-fatal)
        try {
            BookingNoShowReported::dispatch($reportedBooking);
        } catch (\Throwable $e) {
            Log::warning('BookingNoShowReported broadcast failed', [
                'booking_id' => $reportedBooking->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $reportedBooking;
    }

    /**
     * Initiate a hosted checkout payment for an accepted booking.
     * Returns the booking and the FedaPay checkout URL.
     *
     * @return array{booking: Booking, checkout_url: string}
     *
     * @throws ValidationException
     */
    public function initiatePayment(Booking $booking): array
    {
        $lock = Cache::lock("booking_payment_{$booking->id}", 30);

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'status' => ['Un paiement est deja en cours pour ce booking.'],
            ]);
        }

        $initiationKey = null;
        $remotePaymentCreated = false;

        try {
            $prepared = DB::transaction(function () use ($booking): array {
                /** @var Booking $lockedBooking */
                $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

                if ($lockedBooking->status !== BookingStatus::Accepted) {
                    throw ValidationException::withMessages([
                        'status' => ['Ce booking ne peut pas être payé dans son état actuel.'],
                    ]);
                }

                $this->ensureBookingPaymentIsSupported($lockedBooking);

                if ($lockedBooking->fedapay_transaction_id !== null) {
                    return [
                        'booking' => $lockedBooking->fresh(['producer']),
                        'existing_transaction_id' => (int) $lockedBooking->fedapay_transaction_id,
                        'idempotency_key' => null,
                    ];
                }

                $idempotencyKey = $lockedBooking->payment_initiation_key ?: Str::uuid()->toString();
                $lockedBooking->update(['payment_initiation_key' => $idempotencyKey]);

                return [
                    'booking' => $lockedBooking->fresh(['producer']),
                    'existing_transaction_id' => null,
                    'idempotency_key' => $idempotencyKey,
                ];
            });

            if ($prepared['existing_transaction_id'] !== null) {
                $existing = $this->fedapayService->retrieveTransaction($prepared['existing_transaction_id']);
                $terminalFailedStatuses = ['declined', 'canceled', 'refunded'];

                if (! in_array($existing->status, $terminalFailedStatuses, true)) {
                    /** @var object{url:string} $tokenObj */
                    $tokenObj = $existing->generateToken();

                    return [
                        'booking' => Booking::query()->findOrFail($booking->id),
                        'checkout_url' => $tokenObj->url,
                    ];
                }

                $prepared = DB::transaction(function () use ($booking, $prepared): array {
                    /** @var Booking $lockedBooking */
                    $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

                    if ($lockedBooking->status !== BookingStatus::Accepted) {
                        throw ValidationException::withMessages([
                            'status' => ['Ce booking ne peut pas être payé dans son état actuel.'],
                        ]);
                    }

                    if ((int) $lockedBooking->fedapay_transaction_id === $prepared['existing_transaction_id']) {
                        $lockedBooking->update([
                            'fedapay_transaction_id' => null,
                            'payment_mode' => null,
                        ]);
                    }

                    $idempotencyKey = Str::uuid()->toString();
                    $lockedBooking->update(['payment_initiation_key' => $idempotencyKey]);

                    return [
                        'booking' => $lockedBooking->fresh(['producer']),
                        'existing_transaction_id' => null,
                        'idempotency_key' => $idempotencyKey,
                    ];
                });
            }

            $initiationKey = $prepared['idempotency_key'];
            $result = $this->fedapayService->initiatePayment($prepared['booking'], $initiationKey);
            $remotePaymentCreated = true;

            return DB::transaction(function () use ($booking, $result, $initiationKey): array {
                /** @var Booking $lockedBooking */
                $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

                if ($lockedBooking->status !== BookingStatus::Accepted) {
                    throw ValidationException::withMessages([
                        'status' => ['Ce booking ne peut pas être payé dans son état actuel.'],
                    ]);
                }

                if ($lockedBooking->fedapay_transaction_id !== null) {
                    return [
                        'booking' => $lockedBooking->fresh(),
                        'checkout_url' => $result['checkout_url'],
                    ];
                }

                $lockedBooking->update([
                    'fedapay_transaction_id' => $result['fedapay_transaction_id'],
                    'payment_initiation_key' => null,
                ]);

                $this->recordFinancialEvent(
                    FinancialEventType::PaymentInitiated,
                    $lockedBooking,
                    $lockedBooking->montant_total_producteur,
                    ['idempotency_key' => $initiationKey]
                );

                return [
                    'booking' => $lockedBooking->fresh(),
                    'checkout_url' => $result['checkout_url'],
                ];
            });
        } catch (\Throwable $exception) {
            if ($initiationKey !== null && ! $remotePaymentCreated) {
                DB::transaction(function () use ($booking, $initiationKey): void {
                    /** @var Booking $lockedBooking */
                    $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

                    if ($lockedBooking->payment_initiation_key === $initiationKey && $lockedBooking->fedapay_transaction_id === null) {
                        $lockedBooking->update(['payment_initiation_key' => null]);
                    }
                });
            }

            throw $exception;
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Mark a booking as paid after successful Fedapay webhook.
     * Idempotent: skips if booking is already paid.
     *
     * @throws ValidationException
     */
    public function markAsPaid(Booking $booking, string $fedapayRef): Booking
    {
        // Defense-in-depth (resolves deferred-work.md:572): UGC bookings never go
        // through the cash escrow settlement. Even if a UGC fedapay_transaction_id
        // reached this path, it is a no-op here — UGC is settled by
        // UgcCommissionPaymentService (D-1.5.b).
        if ($booking->type_contenu === 'UGC') {
            return $booking;
        }

        return DB::transaction(function () use ($booking, $fedapayRef) {
            $booking = $booking->lockForUpdate()->find($booking->id);

            // Idempotent: already paid, skip
            if ($booking->status === BookingStatus::Paid) {
                return $booking;
            }

            if ($booking->status !== BookingStatus::Accepted) {
                throw ValidationException::withMessages([
                    'status' => ['Ce booking ne peut pas passer au statut payé.'],
                ]);
            }

            // Guard: prevent duplicate PaymentConfirmed FinancialEvent
            if ($this->hasExistingFinancialEvent($booking->id, FinancialEventType::PaymentConfirmed)) {
                return $booking;
            }

            $booking->update(['status' => BookingStatus::Paid]);

            $freshBooking = $booking->fresh();
            $this->escrowService->lock($freshBooking);

            $this->recordFinancialEvent(
                FinancialEventType::PaymentConfirmed,
                $freshBooking,
                $freshBooking->montant_total_producteur,
                ['fedapay_ref' => $fedapayRef, 'status' => 'confirmed'],
            );

            BookingPaid::dispatch($freshBooking);

            return $freshBooking;
        });
    }

    /**
     * Record a failed payment attempt. Does NOT change booking status (stays accepted for retry).
     */
    public function markPaymentFailed(Booking $booking, string $fedapayRef, string $reason): void
    {
        DB::transaction(function () use ($booking, $fedapayRef, $reason) {
            $booking = $booking->lockForUpdate()->find($booking->id);

            // Guard: prevent duplicate PaymentFailed for same fedapay_ref
            if ($this->hasExistingFinancialEvent($booking->id, FinancialEventType::PaymentFailed, $fedapayRef)) {
                return;
            }

            $this->recordFinancialEvent(
                FinancialEventType::PaymentFailed,
                $booking,
                $booking->montant_total_producteur,
                ['fedapay_ref' => $fedapayRef, 'status' => 'failed', 'metadata' => ['reason' => $reason]],
            );
        });
    }

    /**
     * Check Fedapay transaction status and process payment if approved.
     * Fallback for when webhook delivery is unreliable (sandbox / network issues).
     */
    public function checkAndProcessPayment(Booking $booking): Booking
    {
        // Already processed — return as-is
        if (! in_array($booking->status, [BookingStatus::Accepted], true)) {
            return $booking;
        }

        if (! $booking->fedapay_transaction_id) {
            return $booking;
        }

        $transaction = $this->fedapayService->retrieveTransaction($booking->fedapay_transaction_id);

        if ($transaction->status === 'approved') {
            return $this->markAsPaid($booking, $transaction->reference ?? 'fedapay_poll');
        }

        return $booking;
    }

    /**
     * Confirm a booking (double-confirmation logic).
     * - First confirmation: transitions to confirmed_by_face or confirmed_by_producer
     * - Second confirmation: transitions to completed, releases escrow, credits wallet
     *
     * @throws ValidationException
     */
    public function confirm(Booking $booking, User $confirmer): Booking
    {
        return DB::transaction(function () use ($booking, $confirmer) {
            $booking = $booking->lockForUpdate()->find($booking->id);

            if ($booking->date_debut?->isFuture() ?? false) {
                throw ValidationException::withMessages([
                    'date_debut' => ['La confirmation n\'est possible qu\'à partir du jour du tournage.'],
                ]);
            }

            $isFace = ($confirmer->id === $booking->face_id);
            $isProducer = ($confirmer->id === $booking->producer_id);

            $allowedStatuses = [
                BookingStatus::Paid,
                BookingStatus::ConfirmedByFace,
                BookingStatus::ConfirmedByProducer,
            ];

            if (! in_array($booking->status, $allowedStatuses, true)) {
                throw ValidationException::withMessages([
                    'status' => ['Ce booking ne peut pas être confirmé dans son état actuel.'],
                ]);
            }

            $alreadyConfirmedByFace = $booking->status === BookingStatus::ConfirmedByFace;
            $alreadyConfirmedByProducer = $booking->status === BookingStatus::ConfirmedByProducer;

            // Guard: same party already confirmed — idempotent return
            if ($isFace && $alreadyConfirmedByFace) {
                return $booking;
            }
            if ($isProducer && $alreadyConfirmedByProducer) {
                return $booking;
            }

            // Second confirmation → complete
            if ($isFace && $alreadyConfirmedByProducer) {
                return $this->completeBooking($booking);
            }

            if ($isProducer && $alreadyConfirmedByFace) {
                return $this->completeBooking($booking);
            }

            // First confirmation
            $newStatus = $isFace
                ? BookingStatus::ConfirmedByFace
                : BookingStatus::ConfirmedByProducer;

            $booking->update(['status' => $newStatus]);

            BookingPartiallyConfirmed::dispatch($booking->fresh(), $confirmer);

            return $booking->fresh();
        });
    }

    /**
     * Auto-complete a booking (72h timeout without mutual confirmation).
     * Same financial outcome as mutual confirmation but system-initiated.
     */
    public function autoComplete(Booking $booking): void
    {
        DB::transaction(function () use ($booking) {
            $booking = $booking->lockForUpdate()->find($booking->id);

            // Re-fetch under row lock so the command stays idempotent under concurrent runs.
            if ($booking->status === BookingStatus::Completed) {
                return;
            }

            if (! in_array($booking->status, [
                BookingStatus::ConfirmedByFace,
                BookingStatus::ConfirmedByProducer,
            ], true)) {
                return;
            }

            $this->completeBooking($booking);
        });
    }

    /**
     * Expire an unpaid accepted booking after timeout.
     */
    public function expire(Booking $booking): void
    {
        $expired = false;

        DB::transaction(function () use ($booking, &$expired) {
            $booking = $booking->lockForUpdate()->find($booking->id);

            // Idempotent: only accepted bookings can be expired.
            if ($booking->status !== BookingStatus::Accepted) {
                return;
            }

            $booking->update(['status' => BookingStatus::Expired]);
            $expired = true;
        });

        // Dispatch after the transaction commits. A broadcast failure is
        // non-fatal: the booking status is already persisted in DB.
        if ($expired) {
            try {
                BookingExpired::dispatch($booking->fresh());
            } catch (\Throwable $e) {
                Log::warning('BookingExpired broadcast failed', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Expire an unaccepted pending booking after the shooting date has passed.
     */
    public function expireUnaccepted(Booking $booking): void
    {
        $expired = false;
        $cutoff = now();

        DB::transaction(function () use ($booking, &$expired): void {
            $booking = Booking::query()->lockForUpdate()->find($booking->id);

            if ($booking === null) {
                return;
            }

            if ($booking->status !== BookingStatus::Pending) {
                return;
            }

            // A null date_debut (UGC dotation — no shoot date) must never be expired by this
            // shoot-date path; treat null as "future" so the booking is left untouched.
            if ($booking->date_debut?->isFuture() ?? true) {
                return;
            }

            $booking->update([
                'status' => BookingStatus::Expired,
                'accepted_at' => null,
            ]);
            $expired = true;
        });

        if ($expired) {
            try {
                BookingExpired::dispatch($booking->fresh());
            } catch (\Throwable $e) {
                Log::warning('BookingExpired broadcast failed', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Complete a booking: update status, release escrow, credit wallet, dispatch event.
     * MUST be called inside an existing DB::transaction().
     */
    private function completeBooking(Booking $booking): Booking
    {
        $booking->update(['status' => BookingStatus::Completed]);
        $fresh = $booking->fresh();
        $this->escrowService->release($fresh, $this->walletService);
        BookingCompleted::dispatch($fresh);

        return $fresh;
    }

    /**
     * Calculate the base tariff: always tarif_journalier / 8 × duree_heures.
     * Mirrors the frontend preview formula exactly.
     */
    private function calculateTarifBase(Face $face, int $dureeHeures): int
    {
        if ($face->tarif_journalier !== null) {
            return (int) round(($face->tarif_journalier / 8) * $dureeHeures);
        }

        return 0;
    }

    private function ensureBookingPaymentIsSupported(Booking $booking): void
    {
        if ($booking->type_contenu !== 'UGC') {
            return;
        }

        throw ValidationException::withMessages([
            'type_contenu' => ['Le paiement des bookings UGC sera disponible dans une prochaine version.'],
        ]);
    }
}
