<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\CandidatureStatus;
use App\Enums\CompensationType;
use App\Enums\EscrowStatus;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Models\Candidature;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use App\Services\Ugc\UgcCommissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MissionService
{
    public function __construct(
        private readonly MissionPaymentService $missionPaymentService,
        private readonly UgcCommissionService $ugcCommissionService,
        private readonly ProductPhotoService $productPhotoService,
    ) {}

    /**
     * Create a new published mission for a Producer.
     *
     * UGC missions diverge: they are created in `pending_payment` (publish-gate)
     * with a server-recalculated commission, while standard missions are still
     * published directly.
     *
     * @param  array<string, mixed>  $data
     * @return Mission The newly created mission
     */
    public function createMission(Producer $producer, array $data): Mission
    {
        if (($data['type_mission'] ?? null) === MissionType::Ugc->value) {
            return $this->createUgcMission($producer, $data);
        }

        /** @var Mission $mission */
        $mission = $producer->missions()->create([
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

        return $mission;
    }

    /**
     * Create a UGC mission (appel à projets).
     *
     * Produit-seul : créée en `pending_payment` (publish-gate) avec une commission
     * recalculée serveur depuis la valeur produit seule (jamais le cash). Hybride
     * (ugc-8-4, D-8.4.c) : publiée DIRECTEMENT (`published`) SANS paiement et
     * `commission_ugc = null` — la commission WeAct porte uniquement sur le cash,
     * prélevée par-Face à l'acceptation (escrow par-Candidature), pas sur la valeur
     * produit. Le nombre de vidéos est forcé à 2 pour le produit-seul et la cash
     * `budget` est dérivée de `montant_remuneration` (0 pour le produit-seul) —
     * D-1.3.b / D-1.3.d.
     *
     * @param  array<string, mixed>  $data
     */
    private function createUgcMission(Producer $producer, array $data): Mission
    {
        $compensation = $data['type_compensation'];
        $valeurProduit = (int) $data['valeur_produit'];

        $isHybrid = $compensation === CompensationType::Hybrid->value;
        $nombreVideos = $isHybrid ? (int) $data['nombre_videos'] : (int) config('ugc.product_only_video_count', 2);
        $montantRemuneration = $isHybrid ? (int) $data['montant_remuneration'] : 0;

        // ugc-8-4 (D-8.4.c) : hybride publié SANS paiement (commission sur cash, par-Face
        // à l'acceptation) ; produit-seul = gate commission produit (PendingPayment), inchangé.
        $status = $isHybrid ? MissionStatus::Published : MissionStatus::PendingPayment;
        $commission = $isHybrid ? null : $this->ugcCommissionService->compute($valeurProduit);

        // Photos produit (spec photos produit) : création + stockage originaux + rows
        // en transaction — sur throw, ProductPhotoService nettoie les fichiers écrits
        // et la mission est rollbackée. Jobs de variantes afterCommit.
        /** @var list<\Illuminate\Http\UploadedFile> $productPhotos */
        $productPhotos = $data['product_photos'] ?? [];

        return DB::transaction(function () use (
            $producer,
            $data,
            $compensation,
            $valeurProduit,
            $nombreVideos,
            $isHybrid,
            $montantRemuneration,
            $status,
            $commission,
            $productPhotos,
        ): Mission {
            /** @var Mission $mission */
            $mission = $producer->missions()->create([
                'titre' => $data['titre'],
                'description' => $data['description'],
                'date_tournage' => null, // dotation UGC : pas de tournage (invariant serveur, même si input forgé)
                'profil_recherche' => $data['profil_recherche'],
                'budget' => $montantRemuneration,                     // D-1.3.b : dérivé (0 si produit seul)
                'date_limite_candidature' => $data['date_limite_candidature'],
                'nombre_faces_voulu' => $data['nombre_faces_voulu'] ?? 1,
                'type_mission' => MissionType::Ugc->value,
                'type_mission_autre' => null,
                'genre_voulu' => $data['genre_voulu'],
                'lieu' => null, // dotation UGC : pas de lieu de tournage (invariant serveur)
                'duree' => null, // dotation UGC : pas de durée de tournage (invariant serveur)
                'status' => $status,                                  // D-8.4.c : hybride = Published, produit-seul = PendingPayment
                'type_compensation' => $compensation,
                'nom_produit' => $data['nom_produit'],
                'valeur_produit' => $valeurProduit,
                'nombre_videos' => $nombreVideos,
                'montant_remuneration' => $isHybrid ? $montantRemuneration : null,
                'commission_ugc' => $commission,                      // D-8.4.c : null pour l'hybride
            ]);

            // Photos d'une mission = disque PUBLIC (vitrine visible des candidates,
            // URLs asset directes) — décision PO 2026-07-06.
            $this->productPhotoService->attach($mission, $productPhotos, 'public');

            return $mission;
        });
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

        /** @var Mission $freshMission */
        $freshMission = $mission->fresh();

        return $freshMission;
    }

    /**
     * Delete a mission from the database.
     * Auto-cancels active candidatures and notifies affected faces before deleting.
     * This is a hard delete (no soft deletes per PRD).
     */
    public function deleteMission(Mission $mission): void
    {
        $this->cancelActiveCandidatesOnDelete($mission);
        // Photos produit : fichiers (catalogue partagé) + rows AVANT le hard-delete —
        // les colonnes morph n'ont pas de FK cascade (spec photos produit).
        $this->productPhotoService->detachAll($mission);
        $mission->delete();
    }

    /**
     * Cancel all active candidatures and notify faces when a mission is deleted.
     *
     * ugc-9-1 (D-9.1.i) : dénoue d'abord l'escrow hybride de chaque candidature engagée —
     * refund au Producteur (entry Locked) / suppression de l'entry in-flight (Pending) — AVANT
     * le flip Cancelled, le tout sous une DB::transaction (le refund DOIT être transactionnel)
     * et AVANT le hard-delete (cascade FK) du caller deleteMission : 0 argent orphelin.
     */
    private function cancelActiveCandidatesOnDelete(Mission $mission): void
    {
        $activeCandidatures = $mission->candidatures()
            ->whereNotIn('status', [
                CandidatureStatus::Rejected->value,
                CandidatureStatus::Cancelled->value,
            ])
            ->with(['face', 'paymentEntry'])
            ->get();

        DB::transaction(function () use ($mission, $activeCandidatures): void {
            foreach ($activeCandidatures as $candidature) {
                /** @var Candidature $candidature */
                // Dénouement escrow hybride AVANT le flip Cancelled et la cascade FK (D-9.1.i).
                $entry = $candidature->paymentEntry;
                if ($entry !== null && $entry->escrow_status === EscrowStatus::Locked) {
                    $this->missionPaymentService->refundUgcCandidatureEscrow($candidature, 'mission_deleted');
                } elseif ($entry !== null && $entry->escrow_status === EscrowStatus::Pending) {
                    $this->missionPaymentService->markUgcMissionCandidatureFailed($entry, 'mission_deleted');
                }

                $candidature->update(['status' => CandidatureStatus::Cancelled]);

                $faceId = $candidature->face_id;
                $userId = $faceId
                    ? User::where('userable_type', \App\Models\Face::class)
                        ->where('userable_id', $faceId)
                        ->value('id')
                    : null;

                if (! $userId) {
                    continue;
                }

                try {
                    Notification::create([
                        'user_id' => $userId,
                        'type' => 'mission_deleted_candidature_cancelled',
                        'data' => [
                            'message' => "La mission \"{$mission->titre}\" a été supprimée par le producteur. Votre candidature a été annulée.",
                            'mission_id' => $mission->id,
                            'candidature_id' => $candidature->id,
                            'url' => '/face/candidatures',
                        ],
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('MissionDeleted candidature notification failed', [
                        'mission_id' => $mission->id,
                        'candidature_id' => $candidature->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });
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

        /** @var Mission $freshMission */
        $freshMission = $mission->fresh();
        $this->notifyPendingCandidatesOnClose($freshMission);

        return $freshMission;
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
            /** @var Candidature $candidature */
            $userId = data_get($candidature, 'face.userable.id');

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
                        'url' => "/face/candidatures/{$candidature->uuid}",
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

        /** @var Mission $freshMission */
        $freshMission = $mission->fresh();

        return $freshMission;
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
        return DB::transaction(function () use ($mission): Mission {
            if (! $this->missionPaymentService->hasPaidPayment($mission)) {
                throw new \RuntimeException('Mission completion requires a confirmed payment.');
            }

            if ($this->missionPaymentService->hasUnconfirmedSelectedFaces($mission)) {
                throw new \RuntimeException('Mission completion requires all selected faces to confirm participation.');
            }

            // FIX-26.2 BACKWARD-COMPAT BRIDGE — TEMPORARY
            // Auto-mark all `Locked + pending` entries as `present` so the legacy Producer
            // flow (POST /api/v1/producer/missions/{uuid}/complete, called by older clients
            // and existing CompleteMissionTest fixtures) continues to behave as before.
            // FIX-26.4 added the new attendance endpoint
            // (POST /api/v1/producer/missions/{uuid}/validate-attendance) but kept this
            // bridge intact to avoid migrating CompleteMissionTest. Bridge retirement is
            // deferred to FIX-26.10 (legacy auto-release-funds cron deprecation).
            $mission->payment?->entries()
                ->where('escrow_status', EscrowStatus::Locked)
                ->where('attendance_status', AttendanceStatus::Pending)
                ->update(['attendance_status' => AttendanceStatus::Present]);

            // Release funds to selected faces if payment exists
            $this->missionPaymentService->releaseFunds($mission);

            $mission->update([
                'status' => MissionStatus::Completed,
            ]);

            /** @var Mission $freshMission */
            $freshMission = $mission->fresh();
            $this->notifyProducerOnCompletion($freshMission);

            return $freshMission;
        });
    }

    public function notifyProducerOnCompletion(Mission $mission): void
    {
        /** @var User|null $producerUser */
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
                    'url' => "/producer/missions/{$mission->uuid}",
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
