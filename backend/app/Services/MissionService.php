<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class MissionService
{
    public function __construct(
        private readonly MissionPaymentService $missionPaymentService,
    ) {}

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
            'type_mission_autre' => $data['type_mission_autre'] ?? null,
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
        $shootingDateChanged = array_key_exists('date_tournage', $data)
            && $mission->date_tournage?->format('Y-m-d') !== $data['date_tournage'];

        $payload = [
            'titre' => $data['titre'],
            'description' => $data['description'],
            'date_tournage' => $data['date_tournage'],
            'profil_recherche' => $data['profil_recherche'],
            'budget' => $data['budget'],
            'date_limite_candidature' => $data['date_limite_candidature'],
            'nombre_faces_voulu' => $data['nombre_faces_voulu'] ?? 1,
            'type_mission' => $data['type_mission'],
            'type_mission_autre' => $data['type_mission_autre'] ?? null,
            'genre_voulu' => $data['genre_voulu'],
            'lieu' => $data['lieu'],
            'duree' => $data['duree'],
        ];

        if ($shootingDateChanged) {
            $payload['shooting_reminder_sent_at'] = null;
        }

        $mission->update($payload);

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

        $this->notifyPendingCandidatesOnClose($mission->fresh());

        return $mission->fresh();
    }

    /**
     * Notify faces with pending candidatures when a mission is manually closed.
     */
    private function notifyPendingCandidatesOnClose(Mission $mission): void
    {
        $pendingCandidatures = $mission->candidatures()
            ->where('status', CandidatureStatus::Pending->value)
            ->with('face.userable')
            ->get();

        foreach ($pendingCandidatures as $candidature) {
            $userId = $candidature->face?->userable?->id;

            if (! $userId) {
                continue;
            }

            try {
                Notification::create([
                    'user_id' => $userId,
                    'type' => 'mission_closed_pending_candidature',
                    'data' => [
                        'message' => "La mission \"{$mission->titre}\" a été clôturée. Votre candidature n'a pas été retenue.",
                        'mission_id' => $mission->id,
                        'candidature_id' => $candidature->id,
                        'url' => "/face/candidatures/{$candidature->id}",
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::warning('MissionClosed pending candidature notification failed', [
                    'mission_id' => $mission->id,
                    'candidature_id' => $candidature->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
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
     * Only closed missions (with paid payment) can be completed.
     * Releases escrowed funds to selected faces.
     * Once completed, the mission cannot be modified (FINAL state).
     *
     * @return Mission The completed mission (fresh from database)
     */
    public function completeMission(Mission $mission): Mission
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($mission): Mission {
            if (! $this->missionPaymentService->hasPaidPayment($mission)) {
                throw new \RuntimeException('Mission completion requires a confirmed payment.');
            }

            if ($this->missionPaymentService->hasUnconfirmedSelectedFaces($mission)) {
                throw new \RuntimeException('Mission completion requires all selected faces to confirm participation.');
            }

            // Release funds to selected faces if payment exists
            $this->missionPaymentService->releaseFunds($mission);

            $mission->update([
                'status' => MissionStatus::Completed,
            ]);

            $this->notifyProducerOnCompletion($mission->fresh());

            return $mission->fresh();
        });
    }

    private function notifyProducerOnCompletion(Mission $mission): void
    {
        $producerUser = User::where('userable_type', Producer::class)
            ->where('userable_id', $mission->producer_id)
            ->first();

        if (! $producerUser) {
            return;
        }

        try {
            Notification::create([
                'user_id' => $producerUser->id,
                'type' => 'mission_completed_producer',
                'data' => [
                    'message' => "La mission \"{$mission->titre}\" est terminée.",
                    'mission_id' => $mission->id,
                    'url' => "/producer/missions/{$mission->id}",
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Mission completion notification for producer failed', [
                'mission_id' => $mission->id,
                'producer_id' => $mission->producer_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
