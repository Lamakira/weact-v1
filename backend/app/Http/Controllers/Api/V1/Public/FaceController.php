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
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FaceController extends Controller
{
    /**
     * TTL of the cached public total (filter-less listing).
     */
    private const TOTAL_CACHE_SECONDS = 60;

    /**
     * Display a paginated list of public Faces.
     *
     * No authentication required - this is a PUBLIC endpoint.
     */
    public function index(ListFacesRequest $request): JsonResponse
    {
        $perPage = $request->getPerPage();
        $page = max(1, $request->getPage());
        ['generation' => $generation, 'latest' => $servesLatest] = $this->resolveGeneration($request);

        // Deferred join: the page is first resolved to a list of face ids on
        // narrow rows, then ONLY those Faces are loaded (with their rating
        // aggregates). The order and the filter semantics are the ones of the
        // former single statement (rank, unranked last, faces.id DESC).
        //
        // Rotation: the order comes from the materialized ranking built by
        // faces:rebuild-listing-ranks (nightly fairness) and permuted by
        // faces:rotate-listing-ranks (carousel). Which generation is
        // served is decided ONCE, above, by resolveGeneration(); each
        // generation is written in a single transaction, so it only ever
        // becomes visible complete. The rank ORDERS, it never FILTERS:
        // eligibility stays live (publiclyListable), so a Face
        // deactivated after the rebuild is just a hole.
        $hasFilter = $request->filled('categorie')
            || $request->filled('niche')
            || $request->filled('ville')
            || $request->filled('search');

        if ($hasFilter) {
            [$ids, $total] = $this->filteredPage($request, $generation, $servesLatest, $perPage, $page);
        } else {
            [$ids, $total] = $this->unfilteredPage($generation, $servesLatest, $perPage, $page);
        }

        $faces = new LengthAwarePaginator(
            $this->loadFacesInOrder($ids),
            $total,
            $perPage,
            $page,
        );

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
     * Filtered request (categorie / niche / ville / search): same single
     * statement as before, but selecting only the ids of the page.
     *
     * @return array{0: list<int>, 1: int}
     */
    private function filteredPage(ListFacesRequest $request, ?int $generation, bool $servesLatest, int $perPage, int $page): array
    {
        $paginator = Face::query()
            ->publiclyListable()
            ->when($request->validated('categorie'), fn ($q, $cat) => $q->whereJsonContains('categories', $cat))
            ->when($request->validated('niche'), fn ($q, $niche) => $q->whereJsonContains('niches', $niche))
            ->when($request->validated('ville'), fn ($q, $ville) => $q->where('ville', $ville))
            ->when($request->validated('search'), function ($q, $search) {
                $escaped = Sql::escapeLike($search);

                return $q->where(function ($query) use ($escaped) {
                    $query->where('prenom', 'like', "%{$escaped}%")
                        ->orWhere('username', 'like', "%{$escaped}%")
                        ->orWhere('bio', 'like', "%{$escaped}%");
                });
            })
            ->leftJoin('face_listing_ranks', function (JoinClause $join) use ($generation, $servesLatest): void {
                $join->on('face_listing_ranks.face_id', '=', 'faces.id');

                $this->constrainGeneration($join, 'face_listing_ranks.generation', $generation, $servesLatest);
            })
            // Unranked Faces (created after the rebuild, or empty table before
            // the first run) sort after ranked ones: `rank IS NULL` is 0 for
            // ranked rows and 1 for unranked — no sentinel value to keep in
            // sync with the column type. faces.id DESC is the deterministic
            // tiebreak (and the whole-list fallback while the table is empty).
            ->orderByRaw('face_listing_ranks.rank is null')
            ->orderBy('face_listing_ranks.rank')
            ->orderBy('faces.id', 'desc')
            ->paginate($perPage, ['faces.id'], 'page', $page);

        /** @var list<int> $ids */
        $ids = $paginator->getCollection()->pluck('id')->map(fn ($id): int => (int) $id)->all();

        return [$ids, $paginator->total()];
    }

    /**
     * Unfiltered request (the hot path): ranked Faces come straight from the
     * (generation, rank) index, unranked ones (created after the rebuild)
     * follow by id DESC. Nothing is sorted across all the active Faces.
     *
     * @return array{0: list<int>, 1: int}
     */
    private function unfilteredPage(?int $generation, bool $servesLatest, int $perPage, int $page): array
    {
        $offset = ($page - 1) * $perPage;

        // Cached 60 s per generation: the total only feeds the page count.
        $total = (int) Cache::remember(
            'public_faces:total:'.($generation ?? 'none'),
            self::TOTAL_CACHE_SECONDS,
            fn (): int => Face::query()->publiclyListable()->count(),
        );

        $rankedIds = DB::table('face_listing_ranks as r')
            ->tap(fn ($q) => $this->constrainGeneration($q, 'r.generation', $generation, $servesLatest))
            ->whereExists($this->activeFaceUser('r.face_id'))
            ->orderBy('r.rank')
            ->orderByDesc('r.face_id')
            ->offset($offset)
            ->limit($perPage)
            ->pluck('r.face_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $missing = $perPage - count($rankedIds);

        if ($missing <= 0) {
            return [$rankedIds, $total];
        }

        // The ranked part is exhausted. When this page already holds ranked
        // rows the unranked ones start at their first; otherwise skip the
        // ranked rows that precede this page.
        $unrankedOffset = 0;

        if ($rankedIds === []) {
            $rankedTotal = DB::table('face_listing_ranks as r')
                ->tap(fn ($q) => $this->constrainGeneration($q, 'r.generation', $generation, $servesLatest))
                ->whereExists($this->activeFaceUser('r.face_id'))
                ->count();

            $unrankedOffset = max(0, $offset - $rankedTotal);
        }

        $unrankedIds = DB::table('faces')
            ->whereExists($this->activeFaceUser('faces.id'))
            ->whereNotExists(function ($q) use ($generation, $servesLatest): void {
                $q->select(DB::raw('1'))
                    ->from('face_listing_ranks as r2')
                    ->whereColumn('r2.face_id', 'faces.id');

                $this->constrainGeneration($q, 'r2.generation', $generation, $servesLatest);
            })
            ->orderByDesc('faces.id')
            ->offset($unrankedOffset)
            ->limit($missing)
            ->pluck('faces.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return [[...$rankedIds, ...$unrankedIds], $total];
    }

    /**
     * Restrict a ranks table (or join) to the generation this request serves.
     *
     * "The current window" is resolved INSIDE the statement, as a correlated
     * subquery: a retention purge committing between a separate SELECT
     * MAX(...) and the ranks read would otherwise leave it matching nothing,
     * and the WHOLE public listing would silently fall back to id DESC.
     * A generation explicitly resolved (pinned by the visitor, or the nightly
     * base of a filtered request) is a fixed number; 0 is impossible
     * (generations start at 1): an empty table matches nothing.
     *
     * @param  \Illuminate\Database\Query\Builder|JoinClause  $query
     */
    private function constrainGeneration($query, string $column, ?int $generation, bool $servesLatest): void
    {
        if ($servesLatest) {
            $query->whereRaw("{$column} = (select max(generation) from face_listing_ranks)");

            return;
        }

        $query->where($column, '=', $generation ?? 0);
    }

    /**
     * EXISTS clause: the Face behind $faceIdColumn has an active user account
     * (same rule as Face::scopePubliclyListable).
     */
    private function activeFaceUser(string $faceIdColumn): \Closure
    {
        return function ($q) use ($faceIdColumn): void {
            $q->select(DB::raw('1'))
                ->from('users')
                ->whereColumn('users.userable_id', $faceIdColumn)
                ->where('users.userable_type', (new Face)->getMorphClass())
                ->where('users.is_active', true);
        };
    }

    /**
     * Load the page's Faces (rating aggregates computed for these ids only)
     * and put them back in the order of $ids.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Face>
     */
    private function loadFacesInOrder(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        $byId = Face::query()
            ->select('faces.*')
            ->whereIn('faces.id', $ids)
            ->with('activeSubscription')
            ->withRatingAggregates()
            ->get()
            ->keyBy('id');

        return new Collection(array_values(array_filter(
            array_map(fn (int $id): ?Face => $byId->get($id), $ids),
        )));
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
            ->withRatingAggregates()
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
