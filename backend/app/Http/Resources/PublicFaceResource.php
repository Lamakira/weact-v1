<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for PUBLIC Face listing display.
 *
 * This resource exposes only public-safe fields for unauthenticated visitors.
 * Sensitive data like tariffs, bio, etc. are excluded.
 *
 * @mixin \App\Models\Face
 */
class PublicFaceResource extends JsonResource
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
            'username' => $this->username,
            'prenom' => $this->prenom,
            'nom' => $this->nom,
            'ville' => $this->ville,
            'categorie' => $this->categorie?->value,
            'categorie_label' => $this->categorie?->label(),
            'is_available' => $this->is_available,
            'profile_photo_thumbnail_url' => $this->thumbnail_url,
            'average_rating' => $this->average_rating,
        ];
    }
}
