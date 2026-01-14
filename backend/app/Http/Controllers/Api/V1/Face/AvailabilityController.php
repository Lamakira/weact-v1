<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Http\Controllers\Controller;
use App\Http\Requests\Face\UpdateAvailabilityRequest;
use App\Models\Face;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    /**
     * Get current availability status.
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
                'is_available' => $face->is_available,
                'availability_badge' => $face->availability_badge,
                'availability_badge_color' => $face->availability_badge_color,
            ],
        ]);
    }

    /**
     * Update availability status.
     */
    public function update(UpdateAvailabilityRequest $request): JsonResponse
    {
        $result = $this->getAuthenticatedFace($request);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $face = $result;

        $face->update($request->validated());

        return response()->json([
            'data' => [
                'is_available' => $face->is_available,
                'availability_badge' => $face->availability_badge,
                'availability_badge_color' => $face->availability_badge_color,
            ],
            'message' => 'Disponibilité mise à jour avec succès',
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
