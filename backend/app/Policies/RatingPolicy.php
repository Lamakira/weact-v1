<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Candidature;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;

class RatingPolicy
{
    /**
     * Determine if user can create a rating for a candidature as a Face.
     *
     * Requirements:
     * 1. User must be a Face
     * 2. Candidature must be completed (canBeRated)
     * 3. Candidature must belong to this Face
     */
    public function createAsFace(User $user, Candidature $candidature): bool
    {
        // User must be a Face
        if ($user->userable_type !== Face::class) {
            return false;
        }

        // Candidature must be completed
        if (! $candidature->canBeRated()) {
            return false;
        }

        // Candidature must belong to this Face
        return $candidature->face_id === $user->userable_id;
    }

    /**
     * Determine if user can create a rating for a candidature as a Producer.
     *
     * Requirements:
     * 1. User must be a Producer
     * 2. Candidature must be completed (canBeRated)
     * 3. Mission must belong to this Producer
     */
    public function createAsProducer(User $user, Candidature $candidature): bool
    {
        // User must be a Producer
        if ($user->userable_type !== Producer::class) {
            return false;
        }

        // Candidature must be completed
        if (! $candidature->canBeRated()) {
            return false;
        }

        // Load the mission relationship to check ownership
        $candidature->loadMissing('mission');

        // Mission must belong to this Producer
        return $candidature->mission->producer_id === $user->userable_id;
    }
}
