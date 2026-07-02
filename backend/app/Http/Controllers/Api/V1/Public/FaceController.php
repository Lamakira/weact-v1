<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Constants\BeninCities;
use App\Enums\FaceCategory;
use App\Enums\FaceNiche;
use App\Enums\FaceSubscriptionPlan;
use App\Enums\FaceSubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Public\ListFacesRequest;
use App\Http\Resources\PublicFaceProfileResource;
use App\Http\Resources\PublicFaceResource;
use App\Models\Face;
use Illuminate\Http\JsonResponse;
use RuntimeException;

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

        // FP-2.6: build the plan -> sort_priority CASE for the tier-priority
        // ORDER BY. The priority integers come from config/face_subscription_tiers.php
        // (Product Decision #9 — config-driven, never hard-coded in SQL); only the
        // bound values vary, the "WHEN ? THEN ?" placeholders are a static literal.
        $tierPriorityCase = '';
        $tierPriorityBindings = [];

        foreach (FaceSubscriptionPlan::cases() as $plan) {
            $tierPriorityCase .= ' WHEN ? THEN ?';
            $tierPriorityBindings[] = $plan->value;
            $tierPriorityBindings[] = $this->tierSortPriority($plan->value);
        }

        $freeSortPriority = $this->tierSortPriority('free');

        $faces = Face::query()
            ->whereHas('user', fn ($q) => $q->where('is_active', true))
            ->with('activeSubscription')
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
            // FP-2.6 key 1 — subscription tier priority (Élite 1 .. Free 4). A
            // correlated subquery maps the representative active subscription's
            // plan to its configured sort_priority; expires_at DESC, id DESC
            // LIMIT 1 picks the SAME row as Face::activeSubscription. A Face
            // with no active row is COALESCE-d to the Free priority.
            ->orderByRaw(
                "COALESCE((
                    SELECT CASE face_subscriptions.plan{$tierPriorityCase} END
                    FROM face_subscriptions
                    WHERE face_subscriptions.face_id = faces.id
                      AND face_subscriptions.status = ?
                      AND face_subscriptions.expires_at > ?
                    ORDER BY face_subscriptions.expires_at DESC, face_subscriptions.id DESC
                    LIMIT 1
                ), ?)",
                [
                    ...$tierPriorityBindings,
                    FaceSubscriptionStatus::Active->value,
                    now(),
                    $freeSortPriority,
                ]
            )
            // FP-2.6 key 2 — manual admin featured boost, within the tier bucket.
            ->orderBy('is_featured', 'desc')
            // FP-2.6 keys 3 & 4 — the FP-1.6 profile-completeness CASE and
            // created_at tiebreak, unchanged.
            ->orderByRaw(
                'CASE
                    WHEN profile_photo IS NOT NULL AND tarif_journalier IS NOT NULL THEN 1
                    WHEN profile_photo IS NOT NULL THEN 2
                    ELSE 3
                END'
            )
            ->orderBy('created_at', 'desc')
            // FP-2.6 review patch — final unique tiebreaker so rows tying on
            // every key above get a deterministic order (stable pagination).
            ->orderBy('id', 'desc')
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
     * Resolve a tier's configured sort priority for the public ordering.
     *
     * Fails loud on a missing or non-integer config value: a broken
     * config/face_subscription_tiers.php must surface, never silently
     * coerce a bad value (null cast to 0, or a fractional like 1.9 cast
     * to 1) and mis-rank a tier in the public list.
     */
    private function tierSortPriority(string $tier): int
    {
        $priority = config("face_subscription_tiers.tiers.{$tier}.capabilities.sort_priority");

        if (! is_int($priority)) {
            throw new RuntimeException(
                "Missing or non-integer sort_priority for tier '{$tier}' in "
                .'config/face_subscription_tiers.php — run `php artisan config:clear`.'
            );
        }

        return $priority;
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
