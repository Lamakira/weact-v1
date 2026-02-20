<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Http\Controllers\Controller;
use App\Http\Requests\Face\UpdateCategoryNicheRequest;
use App\Models\Face;
use App\Services\CategoryNicheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryNicheController extends Controller
{
    public function __construct(
        private readonly CategoryNicheService $categoryNicheService,
    ) {}

    /**
     * Get current category and niche.
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
                'categories' => $face->categoriesWithLabels(),
                'niches' => $face->nichesWithLabels(),
            ],
        ]);
    }

    /**
     * Update category and niche.
     */
    public function update(UpdateCategoryNicheRequest $request): JsonResponse
    {
        $result = $this->getAuthenticatedFace($request);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $face = $result;

        $updatedFace = $this->categoryNicheService->updateCategoryNiche(
            $face,
            $request->validated()
        );

        return response()->json([
            'data' => [
                'categories' => $updatedFace->categoriesWithLabels(),
                'niches' => $updatedFace->nichesWithLabels(),
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
