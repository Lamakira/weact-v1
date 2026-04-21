<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Producer;

use App\Enums\ErrorCodes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Producer\DeleteAgencyLogoRequest;
use App\Http\Requests\Producer\ShowAgencyLogoRequest;
use App\Http\Requests\Producer\ShowBioRequest;
use App\Http\Requests\Producer\UpdateAgencyLogoRequest;
use App\Http\Requests\Producer\UpdateBioRequest;
use App\Http\Requests\Producer\UpdateProfilePhotoRequest;
use App\Http\Resources\ProducerResource;
use App\Models\Producer;
use App\Services\AgencyLogoService;
use App\Services\ProducerProfilePhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProducerProfilePhotoService $profilePhotoService,
        private readonly AgencyLogoService $agencyLogoService
    ) {}

    /**
     * Get the authenticated Producer from the request.
     */
    private function getAuthenticatedProducer(Request $request): ?Producer
    {
        $user = $request->user();

        if (! $user || ! $user->userable instanceof Producer) {
            return null;
        }

        return $user->userable;
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return response()->json(
            ErrorCodes::Forbidden->envelope('Utilisateur non autorisé'),
            403
        );
    }

    /**
     * Get the current Producer's profile.
     */
    public function show(Request $request): JsonResponse
    {
        $producer = $this->getAuthenticatedProducer($request);

        if (! $producer) {
            return $this->unauthorizedResponse();
        }

        return response()->json([
            'data' => new ProducerResource($producer),
        ]);
    }

    /**
     * Update the Producer's profile photo.
     */
    public function updatePhoto(UpdateProfilePhotoRequest $request): JsonResponse
    {
        $producer = $this->getAuthenticatedProducer($request);

        if (! $producer) {
            return $this->unauthorizedResponse();
        }

        $this->profilePhotoService->uploadProfilePhoto(
            $producer,
            $request->file('photo')
        );

        // Refresh the model to get updated URLs
        $producer->refresh();

        return response()->json([
            'data' => new ProducerResource($producer),
            'message' => 'Photo de profil mise à jour',
        ]);
    }

    /**
     * Delete the Producer's profile photo.
     */
    public function deletePhoto(Request $request): JsonResponse
    {
        $producer = $this->getAuthenticatedProducer($request);

        if (! $producer) {
            return $this->unauthorizedResponse();
        }

        $this->profilePhotoService->deleteProfilePhoto($producer);

        // Refresh the model to get updated (null) URLs
        $producer->refresh();

        return response()->json([
            'data' => new ProducerResource($producer),
            'message' => 'Photo de profil supprimée',
        ]);
    }

    /**
     * Get the Producer's bio.
     */
    public function showBio(ShowBioRequest $request): JsonResponse
    {
        /** @var Producer $producer */
        $producer = $request->user()->userable;

        return response()->json([
            'data' => [
                'bio' => $producer->bio,
            ],
        ]);
    }

    /**
     * Update the Producer's bio.
     */
    public function updateBio(UpdateBioRequest $request): JsonResponse
    {
        /** @var Producer $producer */
        $producer = $request->user()->userable;

        $producer->update([
            'bio' => $request->input('bio'),
        ]);

        return response()->json([
            'data' => [
                'bio' => $producer->bio,
            ],
            'message' => 'Bio mise à jour avec succès',
        ]);
    }

    /**
     * Get the Agency's logo.
     */
    public function showLogo(ShowAgencyLogoRequest $request): JsonResponse
    {
        /** @var Producer $producer */
        $producer = $request->user()->userable;

        return response()->json([
            'data' => [
                'agency_logo_url' => $producer->agency_logo_url,
                'agency_logo_thumbnail_url' => $producer->agency_logo_thumbnail_url,
            ],
        ]);
    }

    /**
     * Update the Agency's logo.
     */
    public function updateLogo(UpdateAgencyLogoRequest $request): JsonResponse
    {
        /** @var Producer $producer */
        $producer = $request->user()->userable;

        $this->agencyLogoService->uploadLogo(
            $producer,
            $request->file('logo')
        );

        // Refresh the model to get updated URLs
        $producer->refresh();

        return response()->json([
            'data' => new ProducerResource($producer),
            'message' => 'Logo de l\'agence mis à jour',
        ]);
    }

    /**
     * Delete the Agency's logo.
     */
    public function deleteLogo(DeleteAgencyLogoRequest $request): JsonResponse
    {
        /** @var Producer $producer */
        $producer = $request->user()->userable;

        $this->agencyLogoService->deleteLogo($producer);

        // Refresh the model to get updated (null) URLs
        $producer->refresh();

        return response()->json([
            'data' => new ProducerResource($producer),
            'message' => 'Logo de l\'agence supprimé',
        ]);
    }
}
