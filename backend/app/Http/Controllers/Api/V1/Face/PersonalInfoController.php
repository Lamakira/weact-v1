<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Http\Controllers\Controller;
use App\Http\Requests\Face\UpdatePersonalInfoRequest;
use App\Models\Face;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonalInfoController extends Controller
{
    /**
     * Get current personal info (sexe, date_naissance, nationalite, pays).
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
                'sexe' => $face->sexe?->value,
                'date_naissance' => $face->date_naissance?->format('Y-m-d'),
                'nationalite' => $face->nationalite,
                'pays' => $face->pays,
            ],
        ]);
    }

    /**
     * Update personal info (sexe, date_naissance, nationalite, pays).
     */
    public function update(UpdatePersonalInfoRequest $request): JsonResponse
    {
        $result = $this->getAuthenticatedFace($request);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $face = $result;

        $face->update($request->validated());

        return response()->json([
            'data' => [
                'sexe' => $face->sexe?->value,
                'date_naissance' => $face->date_naissance?->format('Y-m-d'),
                'nationalite' => $face->nationalite,
                'pays' => $face->pays,
            ],
            'message' => 'Informations personnelles mises à jour avec succès',
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