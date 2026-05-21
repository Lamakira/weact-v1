<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Constants\BeninCities;
use App\Enums\FaceCategory;
use App\Enums\FaceNiche;
use App\Enums\FaceSubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Public\ListFacesRequest;
use App\Http\Resources\PublicFaceProfileResource;
use App\Http\Resources\PublicFaceResource;
use App\Models\Face;
use Illuminate\Http\JsonResponse;

class FaceController extends Controller
{
    /**
     * Display a paginated list of public Faces.
     *
     * No authentication required - this is a PUBLIC endpoint.
     */
    public function index(ListFacesRequest $request): JsonResponse
    {
        // Get validated per_page from form request
        $perPage = $request->getPerPage();

        // Query faces with optional filters and eager-loaded average rating
        // Exclude faces whose user account has been deactivated (deleted accounts)
        $faces = Face::query()
            ->whereHas('user', fn ($q) => $q->where('is_active', true))
            ->withAvg('ratingsReceived', 'score')
            ->when($request->validated('categorie'), fn ($q, $cat) => $q->whereJsonContains('categories', $cat))
            ->when($request->validated('niche'), fn ($q, $niche) => $q->whereJsonContains('niches', $niche))
            ->when($request->validated('ville'), fn ($q, $ville) => $q->where('ville', $ville))
            ->when($request->validated('search'), function ($q, $search) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search);

                return $q->where(function ($query) use ($escaped) {
                    $query->where('prenom', 'like', "%{$escaped}%")
                        ->orWhere('nom', 'like', "%{$escaped}%")
                        ->orWhere('username', 'like', "%{$escaped}%")
                        ->orWhere('bio', 'like', "%{$escaped}%");
                });
            })
            ->orderByRaw(
                'CASE
                    WHEN is_featured = 1 OR EXISTS (
                        SELECT 1 FROM face_subscriptions
                        WHERE face_subscriptions.face_id = faces.id
                          AND face_subscriptions.status = ?
                          AND face_subscriptions.expires_at > NOW()
                    ) THEN 0
                    WHEN profile_photo IS NOT NULL AND tarif_journalier IS NOT NULL THEN 1
                    WHEN profile_photo IS NOT NULL THEN 2
                    ELSE 3
                END',
                [
                    FaceSubscriptionStatus::Active->value,
                ]
            )
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => PublicFaceResource::collection($faces),
            'meta' => [
                'current_page' => $faces->currentPage(),
                'last_page' => $faces->lastPage(),
                'per_page' => $faces->perPage(),
                'total' => $faces->total(),
            ],
            'message' => 'Faces retrieved successfully',
        ]);
    }

    /**
     * Return available filter options for the public faces list.
     *
     * Categories and niches from enums, cities aggregated from database.
     */
    public function filterOptions(): JsonResponse
    {
        $categories = array_map(
            fn (FaceCategory $cat) => ['value' => $cat->value, 'label' => $cat->label()],
            FaceCategory::cases()
        );

        $niches = array_map(
            fn (FaceNiche $niche) => ['value' => $niche->value, 'label' => $niche->label()],
            FaceNiche::cases()
        );

        $cities = BeninCities::values();

        return response()->json([
            'data' => [
                'categories' => $categories,
                'niches' => $niches,
                'cities' => $cities,
            ],
            'message' => 'Filter options retrieved successfully',
        ]);
    }

    /**
     * Display a public Face profile.
     *
     * No authentication required - this is a PUBLIC endpoint.
     * Returns limited profile information for visitors.
     */
    public function show(string $username): JsonResponse
    {
        $face = Face::query()
            ->where('username', $username)
            ->whereHas('user', fn ($q) => $q->where('is_active', true))
            ->with(['photos', 'videos', 'experiences', 'user'])
            ->first();

        if (! $face) {
            return response()->json([
                'error' => [
                    'code' => 'FACE_NOT_FOUND',
                    'message' => 'Face non trouvée',
                ],
            ], 404);
        }

        return response()->json([
            'data' => new PublicFaceProfileResource($face),
            'message' => 'Face profile retrieved successfully',
        ]);
    }
}
