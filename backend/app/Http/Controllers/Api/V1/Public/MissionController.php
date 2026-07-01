<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Public\ListPublicMissionsRequest;
use App\Http\Resources\PublicMissionResource;
use App\Models\Mission;
use Illuminate\Http\JsonResponse;

class MissionController extends Controller
{
    /**
     * Base query for published missions with producer eager-loaded.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Mission>
     */
    private function publishedWithProducer(): \Illuminate\Database\Eloquent\Builder
    {
        return Mission::query()
            ->where('status', MissionStatus::Published)
            ->where('type_mission', '!=', MissionType::Ugc->value)
            ->whereProducerActive() // ← remplace le whereHas inline (même SQL, story 3.0)
            ->with(['producer' => fn ($q) => $q
                ->withAvg('ratingsReceived', 'score')
                ->withCount('ratingsReceived'),
            ]);
    }

    /**
     * Display a paginated list of public Missions.
     *
     * No authentication required - this is a PUBLIC endpoint.
     * Only returns missions with status = published.
     */
    public function index(ListPublicMissionsRequest $request): JsonResponse
    {
        $filters = $request->getFilters();
        $perPage = $request->getPerPage();

        $missions = $this->publishedWithProducer()
            ->notExpired() // exclut les missions obsolètes (date limite candidature / tournage passée)
            ->when(! empty($filters['type_mission']), fn ($query) => $query->where('type_mission', $filters['type_mission']))
            ->when(! empty($filters['lieu']), function ($query) use ($filters) {
                $escaped = str_replace(['%', '_'], ['\%', '\_'], (string) $filters['lieu']);

                return $query->where('lieu', 'like', "%{$escaped}%");
            })
            ->when(isset($filters['budget_min']), fn ($query) => $query->where('budget', '>=', $filters['budget_min']))
            ->when(isset($filters['budget_max']), fn ($query) => $query->where('budget', '<=', $filters['budget_max']))
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'data' => PublicMissionResource::collection($missions),
            'meta' => [
                'current_page' => $missions->currentPage(),
                'last_page' => $missions->lastPage(),
                'per_page' => $missions->perPage(),
                'total' => $missions->total(),
            ],
            'message' => 'Missions retrieved successfully',
        ]);
    }

    /**
     * Display a single public Mission detail.
     *
     * No authentication required - this is a PUBLIC endpoint.
     * Only returns missions with status = published.
     * Non-published missions (draft/closed/completed) return 404.
     */
    public function show(string $slug): JsonResponse
    {
        $mission = $this->publishedWithProducer()
            ->where('slug', $slug)
            ->first();

        if (! $mission) {
            return response()->json([
                'error' => [
                    'code' => 'MISSION_NOT_FOUND',
                    'message' => 'Mission non trouvée',
                ],
            ], 404);
        }

        return response()->json([
            'data' => new PublicMissionResource($mission),
            'message' => 'Mission retrieved successfully',
        ]);
    }
}
