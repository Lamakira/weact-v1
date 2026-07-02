<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Exceptions\AlbumQuotaReachedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Face\AddAlbumPhotoRequest;
use App\Http\Resources\FacePhotoResource;
use App\Models\Face;
use App\Models\FacePhoto;
use App\Services\PhotoAlbumService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlbumController extends Controller
{
    public function __construct(
        private readonly PhotoAlbumService $photoAlbumService
    ) {}

    /**
     * List all album photos for the authenticated Face.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->userable_type !== Face::class) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Seuls les Faces peuvent accéder à cette ressource.',
                ],
            ], 403);
        }

        $face = $user->userable;
        $photos = $this->photoAlbumService->getPhotos($face);

        return response()->json([
            'data' => FacePhotoResource::collection($photos),
        ]);
    }

    /**
     * Add a photo to the album.
     */
    public function store(AddAlbumPhotoRequest $request): JsonResponse
    {
        $face = $request->user()->userable;
        $photo = $request->file('photo');

        try {
            $facePhoto = $this->photoAlbumService->addPhoto($face, $photo);

            return response()->json([
                'data' => new FacePhotoResource($facePhoto),
                'message' => 'Photo ajoutée à l\'album',
            ], 201);
        } catch (AlbumQuotaReachedException $e) {
            return response()->json([
                'error' => [
                    'code' => 'ALBUM_QUOTA_REACHED',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'code' => 'ALBUM_FULL',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }

    /**
     * Delete a photo from the album.
     */
    public function destroy(Request $request, FacePhoto $photo): JsonResponse
    {
        $user = $request->user();

        // Check if user is a Face
        if ($user->userable_type !== Face::class) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Seuls les Faces peuvent accéder à cette ressource.',
                ],
            ], 403);
        }

        // Check if the photo belongs to the authenticated Face
        if ($photo->face_id !== $user->userable_id) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Vous n\'êtes pas autorisé à supprimer cette photo.',
                ],
            ], 403);
        }

        $this->photoAlbumService->deletePhoto($photo);

        return response()->json([
            'message' => 'Photo supprimée de l\'album',
        ]);
    }

    /**
     * Reorder album photos.
     */
    public function reorder(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if user is a Face
        if ($user->userable_type !== Face::class) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Seuls les Faces peuvent accéder à cette ressource.',
                ],
            ], 403);
        }

        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'required|integer',
        ]);

        $face = $user->userable;

        try {
            $this->photoAlbumService->reorderPhotos($face, $validated['order']);

            $photos = $this->photoAlbumService->getPhotos($face);

            return response()->json([
                'data' => FacePhotoResource::collection($photos),
                'message' => 'Ordre des photos mis à jour',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'code' => 'REORDER_ERROR',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }
    }
}
