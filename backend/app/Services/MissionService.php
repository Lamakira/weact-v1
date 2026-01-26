<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MissionStatus;
use App\Models\Mission;
use App\Models\Producer;

class MissionService
{
    /**
     * Create a new published mission for a Producer.
     *
     * @param  array<string, mixed>  $data
     * @return Mission The newly created mission
     */
    public function createMission(Producer $producer, array $data): Mission
    {
        return $producer->missions()->create([
            'titre' => $data['titre'],
            'description' => $data['description'],
            'date_tournage' => $data['date_tournage'],
            'profil_recherche' => $data['profil_recherche'],
            'budget' => $data['budget'],
            'date_limite_candidature' => $data['date_limite_candidature'],
            'nombre_faces_voulu' => $data['nombre_faces_voulu'] ?? 1,
            'type_mission' => $data['type_mission'],
            'genre_voulu' => $data['genre_voulu'],
            'lieu' => $data['lieu'],
            'duree' => $data['duree'],
            'status' => MissionStatus::Published,
        ]);
    }

    /**
     * Update an existing mission with validated data.
     *
     * @param  array<string, mixed>  $data
     * @return Mission The updated mission (fresh from database)
     */
    public function updateMission(Mission $mission, array $data): Mission
    {
        $mission->update([
            'titre' => $data['titre'],
            'description' => $data['description'],
            'date_tournage' => $data['date_tournage'],
            'profil_recherche' => $data['profil_recherche'],
            'budget' => $data['budget'],
            'date_limite_candidature' => $data['date_limite_candidature'],
            'nombre_faces_voulu' => $data['nombre_faces_voulu'] ?? 1,
            'type_mission' => $data['type_mission'],
            'genre_voulu' => $data['genre_voulu'],
            'lieu' => $data['lieu'],
            'duree' => $data['duree'],
        ]);

        return $mission->fresh();
    }

    /**
     * Delete a mission from the database.
     * This is a hard delete (no soft deletes per PRD).
     */
    public function deleteMission(Mission $mission): void
    {
        $mission->delete();
    }

    /**
     * Close a mission to stop accepting new candidatures.
     * Only published missions can be closed.
     *
     * @return Mission The closed mission (fresh from database)
     */
    public function closeMission(Mission $mission): Mission
    {
        $mission->update([
            'status' => MissionStatus::Closed,
        ]);

        return $mission->fresh();
    }

    /**
     * Reopen a closed mission to accept candidatures again.
     * Only closed missions can be reopened.
     *
     * @return Mission The reopened mission (fresh from database)
     */
    public function reopenMission(Mission $mission): Mission
    {
        $mission->update([
            'status' => MissionStatus::Published,
        ]);

        return $mission->fresh();
    }

    /**
     * Mark a mission as completed.
     * Only published or closed missions can be completed.
     * Once completed, the mission cannot be modified (FINAL state).
     *
     * @return Mission The completed mission (fresh from database)
     */
    public function completeMission(Mission $mission): Mission
    {
        $mission->update([
            'status' => MissionStatus::Completed,
        ]);

        return $mission->fresh();
    }
}
