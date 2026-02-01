<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Producer;

use App\Enums\ProducerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Producer\UpdateBasicInfoRequest;
use App\Models\Producer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BasicInfoController extends Controller
{
    /**
     * Get current basic info based on producer type.
     * - Agency: returns agency_name
     * - Particulier: returns first_name, last_name
     */
    public function show(Request $request): JsonResponse
    {
        $result = $this->getAuthenticatedProducer($request);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $producer = $result;

        return response()->json([
            'data' => $this->formatBasicInfo($producer),
        ]);
    }

    /**
     * Update basic info based on producer type.
     */
    public function update(UpdateBasicInfoRequest $request): JsonResponse
    {
        $result = $this->getAuthenticatedProducer($request);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $producer = $result;

        $producer->update($request->validated());

        return response()->json([
            'data' => $this->formatBasicInfo($producer),
            'message' => 'Informations mises à jour avec succès',
        ]);
    }

    /**
     * Format basic info response based on producer type.
     *
     * @return array<string, mixed>
     */
    private function formatBasicInfo(Producer $producer): array
    {
        if ($producer->type === ProducerType::Agency) {
            return [
                'type' => 'agency',
                'agency_name' => $producer->agency_name,
            ];
        }

        return [
            'type' => 'particulier',
            'first_name' => $producer->first_name,
            'last_name' => $producer->last_name,
        ];
    }

    /**
     * Get the authenticated Producer from the request.
     */
    private function getAuthenticatedProducer(Request $request): Producer|JsonResponse
    {
        $user = $request->user();

        if ($user->userable_type !== Producer::class) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Accès réservé aux Producteurs',
                ],
            ], 403);
        }

        return Producer::findOrFail($user->userable_id);
    }
}
