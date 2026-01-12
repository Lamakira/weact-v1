<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Http\Controllers\Controller;
use App\Http\Requests\Face\UploadPresentationVideoRequest;
use App\Models\Face;
use App\Services\PresentationVideoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PresentationVideoController extends Controller
{
    public function __construct(
        private readonly PresentationVideoService $videoService
    ) {}

    /**
     * Get the current presentation video info for the authenticated Face.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        // Authorize: user must be a Face
        if ($user->userable_type !== Face::class) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Accès réservé aux Faces',
                ],
            ], 403);
        }

        $face = Face::findOrFail($user->userable_id);

        return response()->json([
            'data' => [
                'presentation_video_url' => $face->presentation_video_url,
                'presentation_video_thumbnail_url' => $face->presentation_video_thumbnail_url,
            ],
        ]);
    }

    /**
     * Upload a new presentation video for the authenticated Face.
     */
    public function store(UploadPresentationVideoRequest $request): JsonResponse
    {
        $user = $request->user();
        $face = Face::findOrFail($user->userable_id);

        $this->videoService->uploadPresentationVideo($face, $request->file('video'));

        // Refresh the model to get updated URLs
        $face->refresh();

        return response()->json([
            'data' => [
                'presentation_video_url' => $face->presentation_video_url,
                'presentation_video_thumbnail_url' => $face->presentation_video_thumbnail_url,
            ],
            'message' => 'Vidéo de présentation uploadée avec succès',
        ], 201);
    }

    /**
     * Delete the presentation video for the authenticated Face.
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        // Authorize: user must be a Face
        if ($user->userable_type !== Face::class) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Accès réservé aux Faces',
                ],
            ], 403);
        }

        $face = Face::findOrFail($user->userable_id);

        if (! $face->presentation_video) {
            return response()->json([
                'error' => [
                    'code' => 'NO_VIDEO',
                    'message' => 'Aucune vidéo de présentation à supprimer',
                ],
            ], 404);
        }

        $this->videoService->deletePresentationVideo($face);

        return response()->json([
            'message' => 'Vidéo supprimée avec succès',
        ]);
    }
}
