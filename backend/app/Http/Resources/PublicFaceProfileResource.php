<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\FaceVideoType;
use App\Models\FaceVideo;
use App\Services\FaceEntitlementService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for PUBLIC Face profile (detail view).
 *
 * Exposes all public-safe fields including bio, photos, videos, and experiences.
 * Sensitive data (tariffs, full last name, internal ids) are excluded.
 *
 * @mixin \App\Models\Face
 */
class PublicFaceProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $entitlement = app(FaceEntitlementService::class);
        $capabilities = $entitlement->capabilities($this->resource);
        $maxPhotos = $capabilities->maxAlbumPhotos;

        $visiblePhotos = $this->photos->filter(fn ($photo) => $photo->position <= $maxPhotos)->values();
        $albumPhotosCount = $visiblePhotos->count();

        $presentationVisible = $capabilities->maxPresentationVideos >= 1;

        $visibleVideos = $this->videos->filter(function (FaceVideo $video) use ($capabilities): bool {
            $quota = $video->type === FaceVideoType::Acting
                ? $capabilities->maxActingVideos
                : $capabilities->maxUgcVideos;

            return $video->position <= $quota;
        })->values();

        return [
            'id' => $this->uuid,
            'username' => $this->username,
            'prenom' => $this->prenom,
            // Initial only: the full last name is never public (no `nom` key here).
            'display_name' => $this->publicDisplayName(),
            'sexe' => $this->sexe?->value,
            'sexe_label' => $this->sexe?->label(),
            'age' => $this->publiclyVisibleAge(),
            'nationalite' => $this->nationalite,
            'langues' => $this->langues ?? [],
            'taille' => $this->taille,
            'bio' => $this->bio,
            'ville' => $this->ville,
            'pays' => $this->pays,
            'formatted_location' => $this->formatted_location,
            'categories' => $this->categoriesWithLabels(),
            'niches' => $this->nichesWithLabels(),
            'is_available' => $this->is_available,
            'profile_photo_url' => $this->profile_photo_url,
            'profile_photo_medium_url' => $this->medium_url,
            'profile_photo_grid_url' => $this->grid_url,
            'profile_photo_large_url' => $this->large_url,
            'presentation_video_url' => $presentationVisible ? $this->presentation_video_url : null,
            'presentation_video_thumbnail_url' => $presentationVisible ? $this->presentation_video_thumbnail_url : null,
            'average_rating' => $this->average_rating,
            'ratings_count' => $this->ratings_count,
            'has_album_photos' => $albumPhotosCount > 0,
            'album_photos_count' => $albumPhotosCount,
            'has_presentation_video' => $presentationVisible && $this->presentation_video !== null,
            'has_elite_badge' => $capabilities->hasEliteBadge,
            'photos' => FacePhotoResource::collection($visiblePhotos),
            'videos' => FaceVideoResource::collection($visibleVideos),
            'experiences' => ExperienceResource::collection($this->whenLoaded('experiences')),
        ];
    }
}
