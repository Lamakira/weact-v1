<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Http\Controllers\Controller;
use App\Http\Requests\Face\UpdateLanguesRequest;
use App\Models\Face;
use App\Services\LanguesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LanguesController extends Controller
{
    public function __construct(
        private readonly LanguesService $languesService,
    ) {}

    /**
     * Get current langues.
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
                'langues' => $face->langues,
            ],
        ]);
    }

    /**
     * Update langues.
     */
    public function update(UpdateLanguesRequest $request): JsonResponse
    {
        $result = $this->getAuthenticatedFace($request);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $face = $result;

        $updatedFace = $this->languesService->updateLangues(
            $face,
            $request->validated()
        );

        return response()->json([
            'data' => [
                'langues' => $updatedFace->langues,
            ],
            'message' => 'Langues mises à jour avec succès',
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
