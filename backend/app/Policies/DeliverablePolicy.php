<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Deliverable;
use App\Models\Producer;
use App\Models\User;

class DeliverablePolicy
{
    /**
     * Seul le Producteur propriétaire du deal statue sur un livrable (valider /
     * rejeter / retouche, UGC 4.3). Asymétrie FK : bookings.producer_id = users.id ;
     * missions.producer_id = producers.id. Auto-découverte (modèle Deliverable →
     * DeliverablePolicy, aucune registration dans AuthServiceProvider).
     */
    public function review(User $user, Deliverable $deliverable): bool
    {
        $deliverable->loadMissing('owner');
        $owner = $deliverable->owner;

        if ($owner instanceof Booking) {
            return $user->id === $owner->producer_id;
        }

        if ($owner instanceof Candidature) {
            $owner->loadMissing('mission');

            return $user->userable_type === Producer::class
                && $owner->mission !== null
                && $user->userable_id === $owner->mission->producer_id;
        }

        return false;
    }
}
