<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdminProducerRequest;
use App\Http\Resources\ProducerResource;
use App\Models\Producer;
use App\Models\User;
use App\Services\Ugc\UgcMediaCleanupService;
use App\Support\Sql;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProducerController extends Controller
{
    /**
     * List all producers with pagination, search, and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Producer::with('user')->withCount('missions')->orderBy('created_at', 'desc');

        // Search by first_name, last_name, agency_name, or user email
        if ($request->filled('search') && is_string($request->query('search'))) {
            $search = Sql::escapeLike($request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('agency_name', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('email', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by type
        if ($request->filled('type') && is_string($request->query('type'))) {
            $query->where('type', $request->query('type'));
        }

        $producers = $query->paginate(15);

        return response()->json([
            'data' => ProducerResource::collection($producers),
            'meta' => [
                'current_page' => $producers->currentPage(),
                'last_page' => $producers->lastPage(),
                'per_page' => $producers->perPage(),
                'total' => $producers->total(),
            ],
        ]);
    }

    /**
     * Show a single producer with full detail.
     */
    public function show(Producer $producer): JsonResponse
    {
        return response()->json([
            'data' => new ProducerResource($this->loadDetailRelations($producer)),
            'message' => 'Profil Producteur récupéré avec succès',
        ]);
    }

    /**
     * Update a producer's admin-editable fields.
     */
    public function update(UpdateAdminProducerRequest $request, Producer $producer): JsonResponse
    {
        $validated = $request->validated();

        $producer->update($validated);

        return response()->json([
            'data' => new ProducerResource($this->loadDetailRelations($producer->refresh())),
            'message' => 'Profil Producteur mis à jour avec succès',
        ]);
    }

    /**
     * Toggle activation status for a producer's user account.
     */
    public function toggleActive(Producer $producer): JsonResponse
    {
        /** @var User|null $user */
        $user = $producer->user;

        if (! $user) {
            return response()->json([
                'error' => [
                    'code' => 'no_user_account',
                    'message' => 'Aucun compte utilisateur associé à ce profil.',
                ],
            ], 422);
        }

        $newStatus = ! $user->is_active;
        $user->is_active = $newStatus;
        $user->save();

        // Revoke all tokens when deactivating
        if (! $newStatus) {
            $user->tokens()->delete();
        }

        return response()->json([
            'data' => new ProducerResource($this->loadDetailRelations($producer->refresh())),
            'message' => $newStatus ? 'Compte activé avec succès' : 'Compte désactivé avec succès',
        ]);
    }

    /**
     * Delete a producer and its associated user account.
     */
    public function destroy(Producer $producer): JsonResponse
    {
        // Check for active missions (Published)
        $activeMissions = $producer->missions()
            ->where('status', MissionStatus::Published)
            ->exists();

        if ($activeMissions) {
            return response()->json([
                'error' => [
                    'code' => 'active_missions',
                    'message' => 'Impossible de supprimer ce producteur. Des missions actives existent.',
                ],
            ], 422);
        }

        // Check for active candidatures across all producer's missions
        $missionIds = $producer->missions()->pluck('id');
        $activeCandidatures = $missionIds->isNotEmpty() && DB::table('candidatures')
            ->whereIn('mission_id', $missionIds)
            ->whereIn('status', [
                CandidatureStatus::Pending->value,
                CandidatureStatus::Accepted->value,
                CandidatureStatus::Confirmed->value,
                CandidatureStatus::InProgress->value,
            ])
            ->exists();

        if ($activeCandidatures) {
            return response()->json([
                'error' => [
                    'code' => 'active_candidatures',
                    'message' => 'Impossible de supprimer ce producteur. Des candidatures en cours existent.',
                ],
            ], 422);
        }

        DB::transaction(function () use ($producer) {
            // Médias UGC (bookings du Producteur + product_photos publiques de
            // mission + tout le sous-arbre candidatures) : fichiers + rows AVANT la
            // cascade. Chemin admin qui contourne deleteMission — les tables enfants
            // morph n'ont pas de FK (orphelinage disque public + DB sinon).
            app(UgcMediaCleanupService::class)->purgeForProducerUser($producer->user);

            /** @var User|null $user */
            $user = $producer->user;

            if ($user !== null) {
                $user->tokens()->delete();
                $user->delete();
            }

            $producer->delete();
        });

        return response()->json([
            'message' => 'Profil Producteur supprimé avec succès',
        ]);
    }

    private function loadDetailRelations(Producer $producer): Producer
    {
        $producer->load(['user', 'missions' => fn ($q) => $q->latest()->limit(50), 'ratingsReceived']);
        $producer->loadCount('missions');

        return $producer;
    }
}
