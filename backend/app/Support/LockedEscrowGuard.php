<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\EscrowStatus;
use App\Models\Booking;
use App\Models\Face;
use App\Models\Producer;

/**
 * Lecture pure : détecte l'existence d'au moins un escrow `Locked` (fonds
 * réellement bloqués) rattaché à une Face ou à un Producteur, à travers les
 * DEUX mécanismes de séquestre du produit :
 *   - candidatures mission : `MissionPaymentCandidature.escrow_status` (cast enum) ;
 *   - bookings hybrides : `EscrowTransaction.status` (colonne string non castée).
 *
 * Aucun mouvement d'argent, aucun dénouement : sert uniquement de garde
 * fail-loud aux suppressions admin — remonter un escrow bloqué au lieu de le
 * détruire silencieusement du suivi.
 */
final class LockedEscrowGuard
{
    /**
     * Vrai si la Face a un escrow `Locked` sur l'une de ses candidatures OU sur
     * l'un de ses bookings (`bookings.face_id = users.id`).
     */
    public static function forFace(Face $face): bool
    {
        $hasLockedCandidatureEscrow = $face->candidatures()
            ->whereHas('paymentEntry', fn ($q) => $q->where('escrow_status', EscrowStatus::Locked))
            ->exists();

        if ($hasLockedCandidatureEscrow) {
            return true;
        }

        return self::bookingEscrowLocked('face_id', $face->user?->id);
    }

    /**
     * Vrai si le Producteur a un escrow `Locked` sur une candidature de l'une de
     * ses missions OU sur l'un de ses bookings (`bookings.producer_id = users.id`).
     */
    public static function forProducer(Producer $producer): bool
    {
        $hasLockedCandidatureEscrow = $producer->missions()
            ->whereHas('candidatures.paymentEntry', fn ($q) => $q->where('escrow_status', EscrowStatus::Locked))
            ->exists();

        if ($hasLockedCandidatureEscrow) {
            return true;
        }

        return self::bookingEscrowLocked('producer_id', $producer->user?->id);
    }

    /**
     * Vrai s'il existe un booking dont la colonne d'acteur (`face_id` ou
     * `producer_id`) vaut `$userId` ET dont l'escrowTransaction est `Locked`.
     * `EscrowTransaction.status` n'est pas casté : on compare au `->value`.
     */
    private static function bookingEscrowLocked(string $column, ?int $userId): bool
    {
        if ($userId === null) {
            return false;
        }

        return Booking::where($column, $userId)
            ->whereHas('escrowTransaction', fn ($q) => $q->where('status', EscrowStatus::Locked->value))
            ->exists();
    }
}
