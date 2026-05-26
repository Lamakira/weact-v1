<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Http\Controllers\Controller;
use App\Http\Requests\Face\UpdateProfilePhotoRequest;
use App\Http\Resources\FaceResource;
use App\Services\ProfilePhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfilePhotoService $profilePhotoService
    ) {}

    /**
     * Get the current Face's profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        /** @var \App\Models\Face $face */
        $face = $user->userable;
        $face->load(['photos', 'videos', 'activeSubscription']);

        return response()->json([
            'data' => new FaceResource($face),
        ]);
    }

    /**
     * Update the Face's profile photo.
     */
    public function updatePhoto(UpdateProfilePhotoRequest $request): JsonResponse
    {
        $user = $request->user();
        $face = $user->userable;

        $this->profilePhotoService->uploadProfilePhoto(
            $face,
            $request->file('photo')
        );

        // Refresh the model to get updated URLs
        $face->refresh();

        return response()->json([
            'data' => new FaceResource($face),
            'message' => 'Photo de profil mise à jour',
        ]);
    }

    /**
     * Delete the Face's profile photo.
     */
    public function deletePhoto(Request $request): JsonResponse
    {
        $user = $request->user();
        $face = $user->userable;

        $this->profilePhotoService->deleteProfilePhoto($face);

        // Refresh the model to get updated (null) URLs
        $face->refresh();

        return response()->json([
            'data' => new FaceResource($face),
            'message' => 'Photo de profil supprimée',
        ]);
    }
}
