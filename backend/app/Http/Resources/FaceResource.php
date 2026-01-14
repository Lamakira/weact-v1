<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Face
 */
class FaceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'username' => $this->username,
            'profile_photo_url' => $this->profile_photo_url,
            'thumbnail_url' => $this->thumbnail_url,
            'presentation_video_url' => $this->presentation_video_url,
            'presentation_video_thumbnail_url' => $this->presentation_video_thumbnail_url,
            'acting_video_url' => $this->acting_video_url,
            'acting_video_thumbnail_url' => $this->acting_video_thumbnail_url,
            'bio' => $this->bio,
            'ville' => $this->ville,
            'quartier' => $this->quartier,
            'pays' => $this->pays,
            'formatted_location' => $this->formatted_location,
            'taille' => $this->taille,
            'poids' => $this->poids,
            'categorie' => $this->categorie?->value,
            'categorie_label' => $this->categorie?->label(),
            'niche' => $this->niche?->value,
            'niche_label' => $this->niche?->label(),
            'experiences' => ExperienceResource::collection($this->whenLoaded('experiences')),
            'experiences_count' => $this->when($this->experiences_count !== null, $this->experiences_count),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
