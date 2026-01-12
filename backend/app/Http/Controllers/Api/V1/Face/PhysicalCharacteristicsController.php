<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Http\Controllers\Controller;
use App\Http\Requests\Face\UpdatePhysicalCharacteristicsRequest;
use App\Models\Face;
use App\Services\PhysicalCharacteristicsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhysicalCharacteristicsController extends Controller
{
    public function __construct(
        private readonly PhysicalCharacteristicsService $physicalCharacteristicsService,
    ) {}

    /**
     * Get current physical characteristics.
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
                'taille' => $face->taille,
                'poids' => $face->poids,
            ],
        ]);
    }

    /**
     * Update physical characteristics.
     */
    public function update(UpdatePhysicalCharacteristicsRequest $request): JsonResponse
    {
        $result = $this->getAuthenticatedFace($request);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $face = $result;

        $updatedFace = $this->physicalCharacteristicsService->updatePhysicalCharacteristics(
            $face,
            $request->validated()
        );

        return response()->json([
            'data' => [
                'taille' => $updatedFace->taille,
                'poids' => $updatedFace->poids,
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
