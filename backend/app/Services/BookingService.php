<?php

declare(strict_types=1);

namespace App\Services;

use App\Concerns\RecordsFinancialEvent;
use App\Enums\BookingCancellationReason;
use App\Enums\BookingStatus;
use App\Enums\FinancialEventType;
use App\Events\BookingAccepted;
use App\Events\BookingCancelled;
use App\Events\BookingCompleted;
use App\Events\BookingCreated;
use App\Events\BookingExpired;
use App\Events\BookingPaid;
use App\Events\BookingRefused;
use App\Models\Booking;
use App\Models\Face;
use App\Models\Notification;
use App\Models\User;
use App\ValueObjects\BookingPricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BookingService
{
    use RecordsFinancialEvent;

    public function __construct(
        private readonly FedapayService $fedapayService,
        private readonly EscrowService $escrowService,
        private readonly WalletService $walletService,
    ) {}

    /**
     * Create a new booking request.
     *
     * @param  array<string, mixed>  $data  Validated request data
     * @param  User  $producer  The authenticated Producer user
     * @return Booking  The newly created booking
     *
     * @throws ValidationException
     */
    public function create(array $data, User $producer): Booking
    {
        $faceUser = User::findOrFail($data['face_id']);
        $face = Face::where('id', $faceUser->userable_id)->firstOrFail();

        // FR11: Validate Face availability
        if (! $face->is_available) {
            throw ValidationException::withMessages([
                'face_id' => ['Cette Face n\'est pas disponible pour le moment.'],
            ]);
        }

        // FR10: Validate minimum 4h duration
        if ($data['duree_heures'] < 4) {
            throw ValidationException::withMessages([
                'duree_heures' => ['La duree minimale est de 4 heures.'],
            ]);
        }

        // FR4: Calculate pricing via BookingPricing VO (server-side recalculation)
        $tarifBase = $this->calculateTarifBase($face, (int) $data['duree_heures']);

        if ($tarifBase === 0) {
            throw ValidationException::withMessages([
                'face_id' => ['Cette Face n\'a pas encore défini ses tarifs.'],
            ]);
        }

        $pricing = new BookingPricing($tarifBase);

        $booking = Booking::create([
            'face_id' => $data['face_id'],
            'producer_id' => $producer->id,
            'status' => BookingStatus::Pending,
            'date_debut' => $data['date_debut'],
            'date_fin' => $data['date_fin'],
            'duree_heures' => $data['duree_heures'],
            'type_contenu' => $data['type_contenu'],
            'message' => $data['message'] ?? null,
            'tarif_base' => $pricing->baseTarif,
            'montant_total_producteur' => $pricing->totalProducerPays,
            'montant_face_recoit' => $pricing->faceReceives,
        ]);

        BookingCreated::dispatch($booking);

        return $booking;
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

            if ($booking->status !== BookingStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => ['Ce booking ne peut pas être accepté dans son état actuel.'],
                ]);
            }

            $booking->update([
                'status' => BookingStatus::Accepted,
                'accepted_at' => now(),
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
        return DB::transaction(function () use ($booking, $reason) {
            $booking = $booking->lockForUpdate()->find($booking->id);

            if (! in_array($booking->status, [BookingStatus::Pending, BookingStatus::Paid], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Ce booking ne peut pas être refusé dans son état actuel.'],
                ]);
            }

            $booking->update([
                'status' => BookingStatus::Refused,
                'cancellation_reason' => $reason,
            ]);

            BookingRefused::dispatch($booking);

            return $booking->fresh();
        });
    }

    /**
     * Cancel a booking (Producer only).
     * - pending/accepted: immediate cancel with no financial operation
     * - paid: refund 85% and mark escrow as refunded
     *
     * @throws ValidationException
     */
    public function cancel(Booking $booking, string $reason): Booking
    {
        return DB::transaction(function () use ($booking, $reason) {
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
                $this->escrowService->refund($booking, $this->fedapayService);
            }

            $booking->update([
                'status' => BookingStatus::CancelledByProducer,
                'cancellation_reason' => BookingCancellationReason::from($reason)->value,
            ]);

            $freshBooking = $booking->fresh();
            BookingCancelled::dispatch($freshBooking);

            return $freshBooking;
        });
    }

    /**
     * Cancel a booking as Face.
     * - accepted: immediate cancel with no financial operation
     * - paid: refund 85% and mark escrow as refunded
     * - after cancellation, apply face rating penalty (+1.0)
     *
     * @throws ValidationException
     */
    public function cancelByFace(Booking $booking, string $reason): Booking
    {
        $cancelledBooking = DB::transaction(function () use ($booking, $reason) {
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
                $this->escrowService->refund($booking, $this->fedapayService);
            }

            $booking->update([
                'status' => BookingStatus::CancelledByFace,
                'cancellation_reason' => BookingCancellationReason::from($reason)->value,
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
     * Initiate a hosted checkout payment for an accepted booking.
     * Returns the booking and the FedaPay checkout URL.
     *
     * @return array{booking: Booking, checkout_url: string}
     *
     * @throws ValidationException
     */
    public function initiatePayment(Booking $booking): array
    {
        return DB::transaction(function () use ($booking) {
            $booking = $booking->lockForUpdate()->find($booking->id);

            // Idempotent: if booking already has a Fedapay transaction, check its actual
            // status via the Fedapay API. Only block if the transaction is still pending/approved.
            // If it was declined/canceled, allow creating a new transaction.
            if ($booking->fedapay_transaction_id !== null) {
                $existing = $this->fedapayService->retrieveTransaction($booking->fedapay_transaction_id);
                $terminalFailedStatuses = ['declined', 'canceled', 'refunded'];

                if (! in_array($existing->status, $terminalFailedStatuses, true)) {
                    // Re-generate a fresh checkout URL for the existing transaction
                    $tokenObj = $existing->generateToken();
                    return ['booking' => $booking, 'checkout_url' => $tokenObj->url];
                }

                // Previous transaction failed — reset to allow a new attempt
                $booking->update(['fedapay_transaction_id' => null, 'payment_mode' => null]);
            }

            if ($booking->status !== BookingStatus::Accepted) {
                throw ValidationException::withMessages([
                    'status' => ['Ce booking ne peut pas être payé dans son état actuel.'],
                ]);
            }

            $event = $this->recordFinancialEvent(
                FinancialEventType::PaymentInitiated,
                $booking,
                $booking->montant_total_producteur,
            );

            $result = $this->fedapayService->initiatePayment($booking, $event->idempotency_key);

            $booking->update([
                'fedapay_transaction_id' => $result['fedapay_transaction_id'],
            ]);

            return ['booking' => $booking->fresh(), 'checkout_url' => $result['checkout_url']];
        });
    }

    /**
     * Mark a booking as paid after successful Fedapay webhook.
     * Idempotent: skips if booking is already paid.
     *
     * @throws ValidationException
     */
    public function markAsPaid(Booking $booking, string $fedapayRef): Booking
    {
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

            $isFace = ($confirmer->id === $booking->face_id);
            $isProducer = ($confirmer->id === $booking->producer_id);

            $allowedStatuses = [
                BookingStatus::Paid,
                BookingStatus::InProgress,
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

            // Notify the other party of partial confirmation (non-fatal)
            try {
                $otherUserId = $isFace ? $booking->producer_id : $booking->face_id;
                $url = $isFace
                    ? "/producer/bookings/{$booking->id}"
                    : "/face/bookings/{$booking->id}";
                $message = $isFace
                    ? 'La Face a confirmé. À votre tour !'
                    : 'Le producteur a confirmé. À votre tour !';

                Notification::create([
                    'user_id' => $otherUserId,
                    'type'    => 'booking_confirmation_pending',
                    'data'    => [
                        'message'    => $message,
                        'booking_id' => $booking->id,
                        'url'        => $url,
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::warning('Partial confirmation notification failed', [
                    'booking_id' => $booking->id,
                    'error'      => $e->getMessage(),
                ]);
            }

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

            // Idempotent: already completed or cancelled
            if ($booking->status === BookingStatus::Completed) {
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
}
