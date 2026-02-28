<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookingStatus;
use App\Events\BookingCreated;
use App\Models\Booking;
use App\Models\Face;
use App\Models\User;
use App\ValueObjects\BookingPricing;
use Illuminate\Validation\ValidationException;

class BookingService
{
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
