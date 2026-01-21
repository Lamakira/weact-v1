<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;

class MissionPolicy
{
    /**
     * Determine whether the user can view any missions.
     * Missions are publicly viewable when published.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the mission.
     * Missions are publicly viewable.
     */
    public function view(?User $user, Mission $mission): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create missions.
     * Only Producers can create missions.
     */
    public function create(User $user): bool
    {
        return $user->userable_type === Producer::class;
    }

    /**
     * Determine whether the user can update the mission.
     * Only the mission owner (Producer) can update.
     */
    public function update(User $user, Mission $mission): bool
    {
        if ($user->userable_type !== Producer::class) {
            return false;
        }

        return $user->userable_id === $mission->producer_id;
    }

    /**
     * Determine whether the user can delete the mission.
     * Only the mission owner (Producer) can delete.
     */
    public function delete(User $user, Mission $mission): bool
    {
        if ($user->userable_type !== Producer::class) {
            return false;
        }

        return $user->userable_id === $mission->producer_id;
    }
}
