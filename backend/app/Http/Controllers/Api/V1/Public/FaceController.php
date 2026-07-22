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
use App\Support\FaceListingRotation;
use App\Support\Sql;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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
        ['generation' => $generation, 'latest' => $servesLatest] = $this->resolveGeneration($request);

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
            // Rotation: the order comes from the materialized ranking built by
            // faces:rebuild-listing-ranks (nightly fairness) and permuted by
            // faces:rotate-listing-ranks (carousel). Which generation is
            // served is decided ONCE, above, by resolveGeneration(); each
            // generation is written in a single transaction, so it only ever
            // becomes visible complete. The rank ORDERS, it never FILTERS:
            // eligibility stays live (publiclyListable above), so a Face
            // deactivated after the rebuild is just a hole.
            ->leftJoin('face_listing_ranks', function (JoinClause $join) use ($generation, $servesLatest): void {
                $join->on('face_listing_ranks.face_id', '=', 'faces.id');

                if ($servesLatest) {
                    // "The current window" is resolved INSIDE the statement, as
                    // a correlated subquery: a retention purge committing
                    // between a separate SELECT MAX(...) and this query would
                    // otherwise leave the join matching nothing at all, and the
                    // WHOLE public listing would silently fall back to id DESC.
                    $join->whereRaw('face_listing_ranks.generation = (select max(generation) from face_listing_ranks)');

                    return;
                }

                // A generation explicitly resolved (pinned by the visitor, or
                // the nightly base of a filtered request). 0 is impossible
                // (generations start at 1): an empty table matches nothing and
                // the whole list falls back to id DESC.
                $join->where('face_listing_ranks.generation', '=', $generation ?? 0);
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
                // The generation actually served. The client echoes it back on
                // the next pages of the SAME browsing session so a rotation
                // firing in between cannot duplicate or skip a Face. Never a
                // URL parameter — useKeepAliveListingGuard compares URL
                // signatures and an extra key there would fake a reload.
                'generation' => $generation,
            ],
            'message' => 'Faces retrieved successfully',
        ]);
    }

    /**
     * Decide which ranking generation this request is ordered by.
     *
     * Four cases, in this order:
     *
     *  0. The carousel is OFF (`face_listing_rotation.tick_minutes <= 0`) →
     *     the latest `nightly` generation, ALWAYS. The kill switch has to give
     *     back exactly the pre-carousel behaviour: serving MAX(generation)
     *     would keep serving the LAST permutation the rotation happened to
     *     write before being switched off, and no pin may override that.
     *  1. A filter is active (`categorie`, `niche`, `ville`, `search`) → the
     *     latest `nightly` generation, ALWAYS. A filtered result is a search,
     *     not a shop window: it must not reshuffle under the visitor every
     *     five minutes. Falls back to MAX(generation) when no generation is
     *     identifiable as nightly (ranks written before the `source` column).
     *  2. The client pins a generation it already received and that generation
     *     still exists → serve it, so its page 2 really is the continuation of
     *     its page 1.
     *  3. Otherwise → the current carousel window, MAX(generation). A pinned
     *     generation purged by the retention window silently lands here:
     *     continuity is a courtesy, never a 4xx.
     *
     * `latest` says whether the caller must join on a CORRELATED MAX(...)
     * subquery instead of the returned number: cases 0-2 name one precise
     * generation, case 3 means "whatever is current when the query runs" and
     * must not be frozen into a value a purge can invalidate in between.
     * `generation` is then only reported back in `meta` (a stale value there
     * costs at most one ignored pin, never a mis-ordered page).
     *
     * `generation` is null only when the table holds nothing at all — the join
     * then matches no row and the listing degrades to its id DESC fallback.
     *
     * @return array{generation: ?int, latest: bool}
     */
    private function resolveGeneration(ListFacesRequest $request): array
    {
        $latest = DB::table('face_listing_ranks')->max('generation');
        $latest = $latest === null ? null : (int) $latest;

        // filled(), not truthiness: presence is the question asked here.
        $hasFilter = $request->filled('categorie')
            || $request->filled('niche')
            || $request->filled('ville')
            || $request->filled('search');

        if (FaceListingRotation::tickMinutes() <= 0 || $hasFilter) {
            $nightly = FaceListingRotation::latestNightlyGeneration();

            return $nightly === null
                ? ['generation' => $latest, 'latest' => true]
                : ['generation' => $nightly, 'latest' => false];
        }

        $requested = $request->getGeneration();

        if ($requested === null || $requested > ($latest ?? 0)) {
            return ['generation' => $latest, 'latest' => true];
        }

        $exists = DB::table('face_listing_ranks')
            ->where('generation', $requested)
            ->exists();

        return $exists
            ? ['generation' => $requested, 'latest' => false]
            : ['generation' => $latest, 'latest' => true];
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
