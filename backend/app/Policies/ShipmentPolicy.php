<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Producer;
use App\Models\Shipment;
use App\Models\User;

class ShipmentPolicy
{
    /**
     * Seul le Producteur propriétaire du deal confirme l'expédition.
     * Rappel FK : bookings.producer_id = users.id ; missions.producer_id = producers.id.
     */
    public function create(User $user, Booking|Candidature $owner): bool
    {
        if ($owner instanceof Booking) {
            return $user->id === $owner->producer_id;
        }

        $owner->loadMissing('mission');

        return $user->userable_type === Producer::class
            && $owner->mission !== null
            && $user->userable_id === $owner->mission->producer_id;
    }

    /**
     * Seule la Face destinataire du deal confirme la réception.
     * Rappel FK : bookings.face_id = users.id ; candidatures.face_id = faces.id.
     */
    public function confirmReceipt(User $user, Shipment $shipment): bool
    {
        $shipment->loadMissing('owner');
        $owner = $shipment->owner;

        if ($owner instanceof Booking) {
            return $user->id === $owner->face_id;
        }

        if ($owner instanceof Candidature) {
            return $user->userable_type === Face::class
                && $user->userable_id === $owner->face_id;
        }

        return false;
    }

    /**
     * Seule la Face destinataire du deal dépose un livrable (upload Unboxing,
     * UGC 4.1). L'ability porte sur le Shipment (la ligne Deliverable n'existe
     * pas encore au moment de l'autz — D-4.1.c). Copie exacte de l'asymétrie FK
     * de confirmReceipt : bookings.face_id = users.id ; candidatures.face_id = faces.id.
     */
    public function uploadDeliverable(User $user, Shipment $shipment): bool
    {
        $shipment->loadMissing('owner');
        $owner = $shipment->owner;

        if ($owner instanceof Booking) {
            return $user->id === $owner->face_id;
        }

        if ($owner instanceof Candidature) {
            return $user->userable_type === Face::class
                && $user->userable_id === $owner->face_id;
        }

        return false;
    }
}
