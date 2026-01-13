<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Enums\FaceCategory;
use App\Enums\FaceNiche;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CategoryNicheOptionsController extends Controller
{
    /**
     * Get list of categories with labels.
     */
    public function categories(): JsonResponse
    {
        $categories = array_map(
            fn (FaceCategory $category) => [
                'value' => $category->value,
                'label' => $category->label(),
            ],
            FaceCategory::cases()
        );

        return response()->json([
            'data' => $categories,
        ]);
    }

    /**
     * Get list of niches with labels.
     */
    public function niches(): JsonResponse
    {
        $niches = array_map(
            fn (FaceNiche $niche) => [
                'value' => $niche->value,
                'label' => $niche->label(),
            ],
            FaceNiche::cases()
        );

        return response()->json([
            'data' => $niches,
        ]);
    }
}
