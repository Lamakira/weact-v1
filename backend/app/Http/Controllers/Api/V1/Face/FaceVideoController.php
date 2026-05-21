<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Enums\FaceVideoType;
use App\Exceptions\VideoQuotaReachedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Face\UploadFaceVideoRequest;
use App\Http\Resources\FaceVideoResource;
use App\Models\Face;
use App\Models\FaceVideo;
use App\Services\FaceVideoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaceVideoController extends Controller
{
    public function __construct(
        private readonly FaceVideoService $videoService
    ) {}

    /**
     * List the authenticated Face's portfolio videos (acting + UGC).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->userable_type !== Face::class) {
            return $this->forbidden();
        }

        /** @var Face $face */
        $face = $user->userable;

        return response()->json([
            'data' => FaceVideoResource::collection($face->videos),
        ]);
    }

    /**
     * Upload a typed portfolio video (acting or UGC).
     */
    public function store(UploadFaceVideoRequest $request): JsonResponse
    {
        /** @var Face $face */
        $face = $request->user()->userable;
        $type = FaceVideoType::from((string) $request->input('type'));

        try {
            $faceVideo = $this->videoService->uploadVideo($face, $type, $request->file('video'));

            return response()->json([
                'data' => new FaceVideoResource($faceVideo),
                'message' => 'Vidéo ajoutée avec succès.',
            ], 201);
        } catch (VideoQuotaReachedException $e) {
            return response()->json([
                'error' => [
                    'code' => 'VIDEO_QUOTA_REACHED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Delete one of the authenticated Face's portfolio videos.
     */
    public function destroy(Request $request, FaceVideo $video): JsonResponse
    {
        $user = $request->user();

        if ($user->userable_type !== Face::class) {
            return $this->forbidden();
        }

        if ($video->face_id !== $user->userable_id) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Vous n\'êtes pas autorisé à supprimer cette vidéo.',
                ],
            ], 403);
        }

        $this->videoService->deleteVideo($video);

        return response()->json([
            'message' => 'Vidéo supprimée avec succès.',
        ]);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'FORBIDDEN',
                'message' => 'Accès réservé aux Faces.',
            ],
        ], 403);
    }
}
