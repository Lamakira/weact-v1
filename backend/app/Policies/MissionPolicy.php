<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\MissionStatus;
use App\Enums\MissionType;
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
        return ! in_array($mission->status, [MissionStatus::Closed, MissionStatus::Completed], true);
    }

    /**
     * Determine whether the user can pay the WeAct commission of a UGC mission.
     * Only the owning Producer, on a pending_payment UGC mission.
     */
    public function payCommission(User $user, Mission $mission): bool
    {
        return $user->userable_type === Producer::class
            && $user->userable_id === $mission->producer_id
            && $mission->type_mission === MissionType::Ugc
            && $mission->status === MissionStatus::PendingPayment;
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
