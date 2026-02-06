<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Enums\MissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Public\ListPublicMissionsRequest;
use App\Http\Resources\PublicMissionResource;
use App\Models\Mission;
use Illuminate\Http\JsonResponse;

class MissionController extends Controller
{
    /**
     * Display a paginated list of public Missions.
     *
     * No authentication required - this is a PUBLIC endpoint.
     * Only returns missions with status = published.
     */
    public function index(ListPublicMissionsRequest $request): JsonResponse
    {
        $perPage = $request->getPerPage();

        $missions = Mission::query()
            ->where('status', MissionStatus::Published)
            ->with(['producer' => fn ($q) => $q
                ->withAvg('ratingsReceived', 'score')
                ->withCount('ratingsReceived'),
            ])
            ->orderBy('created_at', 'desc')
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
}
