<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use App\Services\FaceEntitlementService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for PUBLIC Face profile (detail view).
 *
 * Exposes all public-safe fields including bio, photos, videos, and experiences.
 * Sensitive data (tariffs, full name) are excluded.
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
        $isPremium = $entitlement->isPremium($this->resource);
        $publicLimit = $entitlement->publicAlbumPhotoLimit($this->resource);

        $visiblePhotos = $this->photos->filter(fn ($photo) => $photo->position <= $publicLimit)->values();
        $albumPhotosCount = $visiblePhotos->count();

        /** @var User|null $user */
        $user = $this->user;

        return [
            'id' => $this->uuid,
            'user_id' => $user?->id,
            'username' => $this->username,
            'prenom' => $this->prenom,
            'sexe' => $this->sexe?->value,
            'sexe_label' => $this->sexe?->label(),
            'age' => $this->show_age ? $this->age : null,
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
            'presentation_video_url' => $this->presentation_video_url,
            'presentation_video_thumbnail_url' => $this->presentation_video_thumbnail_url,
            'acting_video_url' => $isPremium ? $this->acting_video_url : null,
            'acting_video_thumbnail_url' => $isPremium ? $this->acting_video_thumbnail_url : null,
            'average_rating' => $this->average_rating,
            'ratings_count' => $this->ratings_count,
            'has_album_photos' => $albumPhotosCount > 0,
            'album_photos_count' => $albumPhotosCount,
            'has_presentation_video' => $this->presentation_video !== null,
            'has_acting_video' => $isPremium && $this->acting_video !== null,
            'photos' => FacePhotoResource::collection($visiblePhotos),
            'experiences' => ExperienceResource::collection($this->whenLoaded('experiences')),
        ];
    }
}
