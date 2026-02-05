<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for PUBLIC Face profile (detail view).
 *
 * This resource exposes extended public-safe fields for unauthenticated visitors.
 * Includes indicators for locked content (photos, videos) but not the content itself.
 * Sensitive data like full name, tariffs, bio, etc. are excluded.
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
        // Load photos relationship if not loaded to check album count
        if (! $this->relationLoaded('photos')) {
            $this->load('photos');
        }

        $albumPhotosCount = $this->photos->count();

        return [
            'id' => $this->id,
            'prenom' => $this->prenom,
            'ville' => $this->ville,
            'categorie' => $this->categorie?->value,
            'categorie_label' => $this->categorie?->label(),
            'niche' => $this->niche?->value,
            'niche_label' => $this->niche?->label(),
            'is_available' => $this->is_available,
            'profile_photo_url' => $this->profile_photo_url,
            'average_rating' => $this->average_rating,
            'ratings_count' => $this->ratings_count,
            'has_album_photos' => $albumPhotosCount > 0,
            'album_photos_count' => $albumPhotosCount,
            'has_presentation_video' => $this->presentation_video !== null,
            'has_acting_video' => $this->acting_video !== null,
        ];
    }
}
