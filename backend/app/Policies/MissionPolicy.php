<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\MissionStatus;
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
     * Only the mission owner (Producer) can update, and mission must be editable.
     */
    public function update(User $user, Mission $mission): bool
    {
        if ($user->userable_type !== Producer::class) {
            return false;
        }

        // Check ownership
        if ($user->userable_id !== $mission->producer_id) {
            return false;
        }

        // Check mission is editable (not closed or completed)
        return !in_array($mission->status, [MissionStatus::Closed, MissionStatus::Completed], true);
    }

    /**
     * Determine whether the user can delete the mission.
     * Only the mission owner (Producer) can delete.
     * Note: Status check is done in DeleteMissionRequest::withValidator() for proper French 422 error message.
     * Note: Candidature check is also done in DeleteMissionRequest for proper French error message.
     */
    public function delete(User $user, Mission $mission): bool
    {
        if ($user->userable_type !== Producer::class) {
            return false;
        }

        // Check ownership only - status validation in FormRequest for proper 422 response
        return $user->userable_id === $mission->producer_id;
    }
}
