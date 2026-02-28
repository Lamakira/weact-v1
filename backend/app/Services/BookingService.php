<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\FinancialEventType;
use App\Events\BookingAccepted;
use App\Events\BookingCreated;
use App\Events\BookingPaid;
use App\Events\BookingRefused;
use App\Models\Booking;
use App\Models\Face;
use App\Models\FinancialEvent;
use App\Models\User;
use App\ValueObjects\BookingPricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
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
     * @throws ValidationException
     */
    public function initiatePayment(Booking $booking, string $paymentMode): Booking
    {
        return DB::transaction(function () use ($booking, $paymentMode) {
            $booking = $booking->lockForUpdate()->find($booking->id);

            if ($booking->status !== BookingStatus::Accepted) {
                throw ValidationException::withMessages([
                    'status' => ['Ce booking ne peut pas être payé dans son état actuel.'],
                ]);
            }

            $idempotencyKey = Str::uuid()->toString();

            FinancialEvent::create([
                'type' => FinancialEventType::PaymentInitiated,
                'booking_id' => $booking->id,
                'amount' => $booking->montant_total_producteur,
                'idempotency_key' => $idempotencyKey,
                'status' => 'pending',
            ]);

            $result = $this->fedapayService->initiatePayment($booking, $paymentMode, $idempotencyKey);

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

            $booking->update(['status' => BookingStatus::Paid]);

            FinancialEvent::create([
                'type' => FinancialEventType::PaymentConfirmed,
                'booking_id' => $booking->id,
                'amount' => $booking->montant_total_producteur,
                'fedapay_ref' => $fedapayRef,
                'idempotency_key' => Str::uuid()->toString(),
                'status' => 'confirmed',
            ]);

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
            $booking->lockForUpdate()->find($booking->id);

            FinancialEvent::create([
                'type' => FinancialEventType::PaymentFailed,
                'booking_id' => $booking->id,
                'amount' => $booking->montant_total_producteur,
                'fedapay_ref' => $fedapayRef,
                'idempotency_key' => Str::uuid()->toString(),
                'status' => 'failed',
                'metadata' => ['reason' => $reason],
            ]);
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
