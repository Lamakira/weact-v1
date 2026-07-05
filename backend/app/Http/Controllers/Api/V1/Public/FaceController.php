<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Constants\BeninCities;
use App\Enums\FaceCategory;
use App\Enums\FaceNiche;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Public\ListFacesRequest;
use App\Http\Resources\PublicFaceProfileResource;
use App\Http\Resources\PublicFaceResource;
use App\Models\Face;
use App\Support\Sql;
use Illuminate\Database\Query\JoinClause;
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
        $perPage = $request->getPerPage();

        $faces = Face::query()
            // The LEFT JOIN below adds face_listing_ranks columns to the row:
            // keep the hydrated model on faces.* only.
            ->select('faces.*')
            ->publiclyListable()
            ->with('activeSubscription')
            ->withAvg('ratingsReceived', 'score')
            ->when($request->validated('categorie'), fn ($q, $cat) => $q->whereJsonContains('categories', $cat))
            ->when($request->validated('niche'), fn ($q, $niche) => $q->whereJsonContains('niches', $niche))
            ->when($request->validated('ville'), fn ($q, $ville) => $q->where('ville', $ville))
            ->when($request->validated('search'), function ($q, $search) {
                $escaped = Sql::escapeLike($search);

                return $q->where(function ($query) use ($escaped) {
                    $query->where('prenom', 'like', "%{$escaped}%")
                        ->orWhere('nom', 'like', "%{$escaped}%")
                        ->orWhere('username', 'like', "%{$escaped}%")
                        ->orWhere('bio', 'like', "%{$escaped}%");
                });
            })
            // Rotation: the order comes from the materialized ranking built
            // nightly by faces:rebuild-listing-ranks. The current generation
            // is MAX(generation) — the rebuild's transactional insert makes
            // the switch atomic, no pointer table needed. The rank ORDERS,
            // it never FILTERS: eligibility stays live (publiclyListable
            // above), so a Face deactivated after the rebuild is just a hole.
            ->leftJoin('face_listing_ranks', function (JoinClause $join): void {
                $join->on('face_listing_ranks.face_id', '=', 'faces.id')
                    ->whereRaw('face_listing_ranks.generation = (select max(generation) from face_listing_ranks)');
            })
            // Unranked Faces (created after the rebuild, or empty table before
            // the first run) sort after ranked ones: `rank IS NULL` is 0 for
            // ranked rows and 1 for unranked — no sentinel value to keep in
            // sync with the column type. faces.id DESC is the deterministic
            // tiebreak (and the whole-list fallback while the table is empty).
            ->orderByRaw('face_listing_ranks.rank is null')
            ->orderBy('face_listing_ranks.rank')
            ->orderBy('faces.id', 'desc')
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
            ->publiclyListable()
            ->with(['photos', 'videos', 'experiences', 'user', 'activeSubscription'])
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
