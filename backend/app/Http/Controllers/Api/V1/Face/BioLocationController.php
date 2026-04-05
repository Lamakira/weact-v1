<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Http\Controllers\Controller;
use App\Http\Requests\Face\UpdateBioLocationRequest;
use App\Models\Face;
use App\Services\BioLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BioLocationController extends Controller
{
    public function __construct(
        private readonly BioLocationService $bioLocationService,
    ) {}

    /**
     * Get current bio and location.
     */
    public function show(Request $request): JsonResponse
    {
        $result = $this->getAuthenticatedFace($request);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $face = $result;

        return response()->json([
            'data' => [
                'bio' => $face->bio,
                'ville' => $face->ville,
                'pays' => $face->pays,
                'formatted_location' => $face->formatted_location,
            ],
        ]);
    }

    /**
     * Update bio and location.
     */
    public function update(UpdateBioLocationRequest $request): JsonResponse
    {
        $result = $this->getAuthenticatedFace($request);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $face = $result;

        $updatedFace = $this->bioLocationService->updateBioLocation(
            $face,
            $request->validated()
        );

        return response()->json([
            'data' => [
                'bio' => $updatedFace->bio,
                'ville' => $updatedFace->ville,
                'pays' => $updatedFace->pays,
                'formatted_location' => $updatedFace->formatted_location,
            ],
            'message' => 'Profil mis à jour avec succès',
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
}
