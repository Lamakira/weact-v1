<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Producer;
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
}
