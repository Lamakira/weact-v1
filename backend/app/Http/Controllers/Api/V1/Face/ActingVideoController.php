<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Http\Controllers\Controller;
use App\Http\Requests\Face\UploadActingVideoRequest;
use App\Models\Face;
use App\Services\ActingVideoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActingVideoController extends Controller
{
    public function __construct(
        private readonly ActingVideoService $videoService
    ) {}

    /**
     * Get the current acting video info for the authenticated Face.
     */
    public function show(Request $request): JsonResponse
    {
        $face = $this->getAuthenticatedFace($request);
        if ($face instanceof JsonResponse) {
            return $face;
        }

        return response()->json([
            'data' => [
                'acting_video_url' => $face->acting_video_url,
                'acting_video_thumbnail_url' => $face->acting_video_thumbnail_url,
            ],
        ]);
    }

    /**
     * Upload a new acting video for the authenticated Face.
     */
    public function store(UploadActingVideoRequest $request): JsonResponse
    {
        $user = $request->user();
        $face = Face::findOrFail($user->userable_id);

        $this->videoService->uploadActingVideo($face, $request->file('video'));

        // Refresh the model to get updated URLs
        $face->refresh();

        return response()->json([
            'data' => [
                'acting_video_url' => $face->acting_video_url,
                'acting_video_thumbnail_url' => $face->acting_video_thumbnail_url,
            ],
            'message' => 'Vidéo d\'acting uploadée avec succès',
        ], 201);
    }

    /**
     * Delete the acting video for the authenticated Face.
     */
    public function destroy(Request $request): JsonResponse
    {
        $face = $this->getAuthenticatedFace($request);
        if ($face instanceof JsonResponse) {
            return $face;
        }

        if (! $face->acting_video) {
            return response()->json([
                'error' => [
                    'code' => 'NO_VIDEO',
                    'message' => 'Aucune vidéo d\'acting à supprimer',
                ],
            ], 404);
        }

        $this->videoService->deleteActingVideo($face);

        return response()->json([
            'message' => 'Vidéo supprimée avec succès',
        ]);
    }

    /**
     * Get the authenticated Face or return a forbidden response.
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
