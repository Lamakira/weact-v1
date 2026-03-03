<?php

declare(strict_types=1);

namespace App\Services;

use App\Concerns\RecordsFinancialEvent;
use App\Enums\BookingStatus;
use App\Enums\FinancialEventType;
use App\Events\BookingAccepted;
use App\Events\BookingCreated;
use App\Events\BookingPaid;
use App\Events\BookingRefused;
use App\Models\Booking;
use App\Models\Face;
use App\Models\User;
use App\ValueObjects\BookingPricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    use RecordsFinancialEvent;

    public function __construct(
        private readonly FedapayService $fedapayService,
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

            $booking->update(['status' => BookingStatus::Accepted]);

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
     * Initiate a Mobile Money payment for an accepted booking.
     *
     * @param  array{number: string, country: string}  $phoneData
     *
     * @throws ValidationException
     */
    public function initiatePayment(Booking $booking, string $paymentMode, array $phoneData): Booking
    {
        return DB::transaction(function () use ($booking, $paymentMode, $phoneData) {
            $booking = $booking->lockForUpdate()->find($booking->id);

            // Idempotent: if booking already has a Fedapay transaction, check its actual
            // status via the Fedapay API. Only block if the transaction is still pending/approved.
            // If it was declined/canceled, allow creating a new transaction.
            if ($booking->fedapay_transaction_id !== null) {
                $existing = $this->fedapayService->retrieveTransaction($booking->fedapay_transaction_id);
                $terminalFailedStatuses = ['declined', 'canceled', 'refunded'];

                if (! in_array($existing->status, $terminalFailedStatuses, true)) {
                    return $booking;
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

            $result = $this->fedapayService->initiatePayment($booking, $paymentMode, $event->idempotency_key, $phoneData);

            $booking->update([
                'fedapay_transaction_id' => $result['fedapay_transaction_id'],
                'payment_mode' => $paymentMode,
            ]);

            return $booking->fresh();
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

            $this->recordFinancialEvent(
                FinancialEventType::PaymentConfirmed,
                $booking,
                $booking->montant_total_producteur,
                ['fedapay_ref' => $fedapayRef, 'status' => 'confirmed'],
            );

            BookingPaid::dispatch($booking);

            return $booking->fresh();
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
     * Calculate the base tariff based on Face rates and duration.
     * Uses daily rate if duration >= 8h, otherwise hourly rate.
     */
    private function calculateTarifBase(Face $face, int $dureeHeures): int
    {
        if ($dureeHeures >= 8 && $face->tarif_journalier !== null) {
            $days = (int) ceil($dureeHeures / 8);

            return $days * $face->tarif_journalier;
        }

        if ($face->tarif_horaire !== null) {
            return $dureeHeures * $face->tarif_horaire;
        }

        // Fallback to daily rate prorated
        if ($face->tarif_journalier !== null) {
            return (int) round(($face->tarif_journalier / 8) * $dureeHeures);
        }

        return 0;
    }
}
