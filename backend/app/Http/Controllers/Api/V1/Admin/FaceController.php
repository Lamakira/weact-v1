<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\CandidatureStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdminFaceRequest;
use App\Http\Resources\FaceResource;
use App\Models\Face;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FaceController extends Controller
{
    /**
     * List all faces with pagination, search, and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Face::with(['user', 'activeSubscription'])->orderBy('created_at', 'desc');

        // Search by nom, prenom, username, or user email
        if ($request->filled('search') && is_string($request->query('search'))) {
            $search = str_replace(['%', '_'], ['\%', '\_'], $request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('prenom', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('email', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by category
        if ($request->filled('category') && is_string($request->query('category'))) {
            $query->whereJsonContains('categories', $request->query('category'));
        }

        // Filter by availability
        if ($request->has('is_available')) {
            $query->where('is_available', filter_var($request->query('is_available'), FILTER_VALIDATE_BOOLEAN));
        }

        $faces = $query->paginate(15);

        return response()->json([
            'data' => FaceResource::collection($faces),
            'meta' => [
                'current_page' => $faces->currentPage(),
                'last_page' => $faces->lastPage(),
                'per_page' => $faces->perPage(),
                'total' => $faces->total(),
            ],
        ]);
    }

    /**
     * Show a single face with full detail.
     */
    public function show(Face $face): JsonResponse
    {
        return response()->json([
            'data' => new FaceResource($this->loadDetailRelations($face)),
            'message' => 'Profil Face récupéré avec succès',
        ]);
    }

    /**
     * Update a face's admin-editable fields.
     */
    public function update(UpdateAdminFaceRequest $request, Face $face): JsonResponse
    {
        $validated = $request->validated();

        $face->update($validated);

        return response()->json([
            'data' => new FaceResource($this->loadDetailRelations($face->refresh())),
            'message' => 'Profil Face mis à jour avec succès',
        ]);
    }

    /**
     * Toggle activation status for a face's user account.
     */
    public function toggleActive(Face $face): JsonResponse
    {
        $user = $face->user;

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
            'data' => new FaceResource($this->loadDetailRelations($face->refresh())),
            'message' => $newStatus ? 'Compte activé avec succès' : 'Compte désactivé avec succès',
        ]);
    }

    /**
     * Delete a face and its associated user account.
     */
    public function destroy(Face $face): JsonResponse
    {
        // Check for active candidatures
        $activeCandidatures = $face->candidatures()
            ->whereIn('status', [
                CandidatureStatus::Pending,
                CandidatureStatus::Accepted,
                CandidatureStatus::Confirmed,
                CandidatureStatus::InProgress,
            ])
            ->exists();

        if ($activeCandidatures) {
            return response()->json([
                'error' => [
                    'code' => 'active_candidatures',
                    'message' => 'Impossible de supprimer ce profil. Des candidatures actives existent.',
                ],
            ], 422);
        }

        DB::transaction(function () use ($face) {
            // Delete associated user first
            $face->user?->tokens()->delete();
            $face->user?->delete();
            $face->delete();
        });

        return response()->json([
            'message' => 'Profil Face supprimé avec succès',
        ]);
    }

    private function loadDetailRelations(Face $face): Face
    {
        $face->load(['user', 'photos', 'experiences', 'ratingsReceived', 'activeSubscription']);
        $face->loadCount(['candidatures', 'photos', 'experiences']);

        return $face;
    }
}
