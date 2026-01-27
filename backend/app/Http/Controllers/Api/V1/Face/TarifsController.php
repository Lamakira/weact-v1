<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Http\Controllers\Controller;
use App\Http\Requests\Face\UpdateTarifsRequest;
use App\Models\Face;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TarifsController extends Controller
{
    /**
     * Get current tarifs.
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
                'tarif_horaire' => $face->tarif_horaire,
                'tarif_journalier' => $face->tarif_journalier,
                'formatted_tarif_horaire' => $face->formatted_tarif_horaire,
                'formatted_tarif_journalier' => $face->formatted_tarif_journalier,
            ],
        ]);
    }

    /**
     * Update tarifs.
     */
    public function update(UpdateTarifsRequest $request): JsonResponse
    {
        $result = $this->getAuthenticatedFace($request);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $face = $result;

        $face->update($request->validated());

        return response()->json([
            'data' => [
                'tarif_horaire' => $face->tarif_horaire,
                'tarif_journalier' => $face->tarif_journalier,
                'formatted_tarif_horaire' => $face->formatted_tarif_horaire,
                'formatted_tarif_journalier' => $face->formatted_tarif_journalier,
            ],
            'message' => 'Tarifs mis à jour avec succès',
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
