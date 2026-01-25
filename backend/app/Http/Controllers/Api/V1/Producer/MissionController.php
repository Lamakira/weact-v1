<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Producer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mission\StoreMissionRequest;
use App\Http\Requests\Mission\UpdateMissionRequest;
use App\Http\Resources\MissionResource;
use App\Models\Mission;
use App\Models\Producer;
use App\Services\MissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class MissionController extends Controller
{
    public function __construct(
        private readonly MissionService $missionService,
    ) {}

    /**
     * Display the specified mission.
     */
    public function show(Mission $mission): JsonResponse
    {
        Gate::authorize('view', $mission);

        return response()->json([
            'data' => new MissionResource($mission->load('producer')),
        ]);
    }

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

    /**
     * Update the specified mission.
     * Authorization is handled by UpdateMissionRequest::authorize()
     * Status validation is handled by UpdateMissionRequest::withValidator()
     */
    public function update(UpdateMissionRequest $request, Mission $mission): JsonResponse
    {
        $updatedMission = $this->missionService->updateMission(
            $mission,
            $request->validated()
        );

        return response()->json([
            'data' => new MissionResource($updatedMission->load('producer')),
            'message' => 'Mission modifiée avec succès',
        ]);
    }
}
