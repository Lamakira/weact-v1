<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Http\Controllers\Controller;
use App\Http\Requests\Face\StoreExperienceRequest;
use App\Http\Requests\Face\UpdateExperienceRequest;
use App\Http\Resources\ExperienceResource;
use App\Models\Experience;
use App\Models\Face;
use App\Services\ExperienceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExperienceController extends Controller
{
    public function __construct(
        private readonly ExperienceService $experienceService,
    ) {}

    /**
     * List all experiences for the authenticated Face.
     */
    public function index(Request $request): JsonResponse
    {
        $result = $this->getAuthenticatedFace($request);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $face = $result;
        $experiences = $this->experienceService->getExperiences($face);

        return response()->json([
            'data' => ExperienceResource::collection($experiences),
            'message' => 'Expériences récupérées avec succès',
        ]);
    }

    /**
     * Create a new experience.
     */
    public function store(StoreExperienceRequest $request): JsonResponse
    {
        $result = $this->getAuthenticatedFace($request);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $face = $result;
        $experience = $this->experienceService->createExperience(
            $face,
            $request->validated()
        );

        return response()->json([
            'data' => new ExperienceResource($experience),
            'message' => 'Expérience ajoutée avec succès',
        ], 201);
    }

    /**
     * Get a single experience.
     */
    public function show(Request $request, Experience $experience): JsonResponse
    {
        $ownershipCheck = $this->verifyOwnership($request, $experience);

        if ($ownershipCheck instanceof JsonResponse) {
            return $ownershipCheck;
        }

        return response()->json([
            'data' => new ExperienceResource($experience),
        ]);
    }

    /**
     * Update an experience.
     */
    public function update(UpdateExperienceRequest $request, Experience $experience): JsonResponse
    {
        // Authorization is already handled in UpdateExperienceRequest
        $updatedExperience = $this->experienceService->updateExperience(
            $experience,
            $request->validated()
        );

        return response()->json([
            'data' => new ExperienceResource($updatedExperience),
            'message' => 'Expérience mise à jour avec succès',
        ]);
    }

    /**
     * Delete an experience.
     */
    public function destroy(Request $request, Experience $experience): JsonResponse
    {
        $ownershipCheck = $this->verifyOwnership($request, $experience);

        if ($ownershipCheck instanceof JsonResponse) {
            return $ownershipCheck;
        }

        $this->experienceService->deleteExperience($experience);

        return response()->json([
            'message' => 'Expérience supprimée avec succès',
        ]);
    }

    /**
     * Get the authenticated Face from the request.
     */
    private function getAuthenticatedFace(Request $request): Face|JsonResponse
    {
        $user = $request->user();

        if ($user->userable_type !== Face::class) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Accès réservé aux Faces',
                ],
            ], 403);
        }

        return Face::findOrFail($user->userable_id);
    }

    /**
     * Verify that the experience belongs to the authenticated Face.
     */
    private function verifyOwnership(Request $request, Experience $experience): ?JsonResponse
    {
        $user = $request->user();

        if ($user->userable_type !== Face::class) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Accès réservé aux Faces',
                ],
            ], 403);
        }

        if ($experience->face_id !== $user->userable_id) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => "Vous n'êtes pas autorisé à modifier cette expérience",
                ],
            ], 403);
        }

        return null;
    }
}
