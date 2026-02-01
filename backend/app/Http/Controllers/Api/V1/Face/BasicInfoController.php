<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Http\Controllers\Controller;
use App\Http\Requests\Face\UpdateBasicInfoRequest;
use App\Models\Face;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BasicInfoController extends Controller
{
    /**
     * Get current basic info (nom, prenom, username).
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
                'nom' => $face->nom,
                'prenom' => $face->prenom,
                'username' => $face->username,
            ],
        ]);
    }

    /**
     * Update basic info (nom, prenom, username).
     */
    public function update(UpdateBasicInfoRequest $request): JsonResponse
    {
        $result = $this->getAuthenticatedFace($request);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $face = $result;

        $face->update($request->validated());

        return response()->json([
            'data' => [
                'nom' => $face->nom,
                'prenom' => $face->prenom,
                'username' => $face->username,
            ],
            'message' => 'Informations mises à jour avec succès',
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
