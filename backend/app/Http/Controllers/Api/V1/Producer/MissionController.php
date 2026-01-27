<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Producer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mission\CloseMissionRequest;
use App\Http\Requests\Mission\CompleteMissionRequest;
use App\Http\Requests\Mission\DeleteMissionRequest;
use App\Http\Requests\Mission\ReopenMissionRequest;
use App\Http\Requests\Mission\StoreMissionRequest;
use App\Http\Requests\Mission\UpdateMissionRequest;
use App\Http\Resources\MissionResource;
use App\Models\Mission;
use App\Models\Producer;
use App\Services\MissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MissionController extends Controller
{
    public function __construct(
        private readonly MissionService $missionService,
    ) {}

    /**
     * Display a listing of the producer's missions.
     * Filtered by authenticated producer, ordered by most recent first.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only Producers can access this endpoint
        if ($user->userable_type !== Producer::class) {
            abort(403, 'Cette action n\'est pas autorisée');
        }

        $missions = Mission::where('producer_id', $user->userable_id)
            ->with('producer')
            ->withCount('candidatures')
            ->orderBy('created_at', 'desc')
            ->get();

        $message = $missions->isEmpty()
            ? 'Aucune mission trouvée'
            : 'Missions récupérées avec succès';

        return response()->json([
            'data' => MissionResource::collection($missions),
            'message' => $message,
        ]);
    }

    /**
     * Display the specified mission.
     * For producer routes, only the owner can view their mission details.
     */
    public function show(Request $request, Mission $mission): JsonResponse
    {
        $user = $request->user();

        // For producer routes, check ownership
        if ($user->userable_type !== Producer::class) {
            abort(403, 'Cette action n\'est pas autorisée');
        }

        if ($user->userable_id !== $mission->producer_id) {
            abort(403, 'Cette mission ne vous appartient pas');
        }

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

    /**
     * Delete the specified mission.
     * Authorization is handled by DeleteMissionRequest::authorize()
     * Status validation is handled by DeleteMissionRequest::withValidator()
     *
     * @param DeleteMissionRequest $request Type-hint triggers FormRequest validation (not used directly)
     */
    public function destroy(DeleteMissionRequest $request, Mission $mission): JsonResponse
    {
        $this->missionService->deleteMission($mission);

        return response()->json([
            'message' => 'Mission supprimée avec succès',
        ]);
    }

    /**
     * Close a mission to stop accepting new candidatures.
     * Authorization is handled by CloseMissionRequest::authorize()
     * Status validation is handled by CloseMissionRequest::withValidator()
     *
     * @param CloseMissionRequest $request Type-hint triggers FormRequest validation (not used directly)
     */
    public function close(CloseMissionRequest $request, Mission $mission): JsonResponse
    {
        $closedMission = $this->missionService->closeMission($mission);

        return response()->json([
            'data' => new MissionResource($closedMission->load('producer')),
            'message' => 'Mission clôturée avec succès',
        ]);
    }

    /**
     * Reopen a closed mission to accept candidatures again.
     * Authorization is handled by ReopenMissionRequest::authorize()
     * Status validation is handled by ReopenMissionRequest::withValidator()
     *
     * @param ReopenMissionRequest $request Type-hint triggers FormRequest validation (not used directly)
     */
    public function reopen(ReopenMissionRequest $request, Mission $mission): JsonResponse
    {
        $reopenedMission = $this->missionService->reopenMission($mission);

        return response()->json([
            'data' => new MissionResource($reopenedMission->load('producer')),
            'message' => 'Mission réouverte avec succès',
        ]);
    }

    /**
     * Mark a mission as completed.
     * Authorization is handled by CompleteMissionRequest::authorize()
     * Status validation is handled by CompleteMissionRequest::withValidator()
     *
     * @param CompleteMissionRequest $request Type-hint triggers FormRequest validation (not used directly)
     */
    public function complete(CompleteMissionRequest $request, Mission $mission): JsonResponse
    {
        $completedMission = $this->missionService->completeMission($mission);

        return response()->json([
            'data' => new MissionResource($completedMission->load('producer')),
            'message' => 'Mission marquée comme terminée',
        ]);
    }
}
