<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Producer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mission\StoreMissionRequest;
use App\Http\Resources\MissionResource;
use App\Models\Producer;
use App\Services\MissionService;
use Illuminate\Http\JsonResponse;

class MissionController extends Controller
{
    public function __construct(
        private readonly MissionService $missionService,
    ) {}

    /**
     * Store a newly created mission.
     */
    public function store(StoreMissionRequest $request): JsonResponse
    {
        $user = $request->user();

        // Get the Producer from the polymorphic relationship
        $producer = Producer::findOrFail($user->userable_id);

        $mission = $this->missionService->createMission(
            $producer,
            $request->validated()
        );

        return response()->json([
            'data' => new MissionResource($mission),
            'message' => 'Mission publiée avec succès',
        ], 201);
    }
}
