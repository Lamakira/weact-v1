<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Admin;
use App\Models\Face;
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
            'sexe' => $this->sexe?->value,
            'sexe_label' => $this->sexe?->label(),
            'age' => $this->resolveAge($request),
            'nationalite' => $this->nationalite,
            'langues' => $this->langues,
            'profile_photo_url' => $this->profile_photo_url,
            'thumbnail_url' => $this->thumbnail_url,
            'presentation_video_url' => $this->presentation_video_url,
            'presentation_video_thumbnail_url' => $this->presentation_video_thumbnail_url,
            'acting_video_url' => $this->acting_video_url,
            'acting_video_thumbnail_url' => $this->acting_video_thumbnail_url,
            'bio' => $this->bio,
            'ville' => $this->ville,
            'pays' => $this->pays,
            'whatsapp_number' => $this->whatsapp_number,
            'formatted_location' => $this->formatted_location,
            'taille' => $this->taille,
            'poids' => $this->poids,
            'categories' => $this->categoriesWithLabels(),
            'niches' => $this->nichesWithLabels(),
            'tarif_horaire' => $this->tarif_horaire,
            'tarif_journalier' => $this->tarif_journalier,
            'formatted_tarif_horaire' => $this->formatted_tarif_horaire,
            'formatted_tarif_journalier' => $this->formatted_tarif_journalier,
            'is_available' => $this->is_available,
            'is_featured' => $this->is_featured,
            'availability_badge' => $this->availability_badge,
            'availability_badge_color' => $this->availability_badge_color,
            'profile_completion_percentage' => $this->profile_completion_percentage,
            'profile_completion_missing' => $this->profile_completion_missing,
            'profile_completion_is_complete' => $this->profile_completion_is_complete,
            'average_rating' => $this->average_rating,
            'ratings_count' => $this->ratings_count,
            'email' => $this->whenLoaded('user', fn () => $this->user?->email),
            'is_active' => $this->whenLoaded('user', fn () => $this->user?->is_active),
            'experiences' => ExperienceResource::collection($this->whenLoaded('experiences')),
            'experiences_count' => $this->when($this->experiences_count !== null, $this->experiences_count),
            'photos' => FacePhotoResource::collection($this->whenLoaded('photos')),
            'photos_count' => $this->when($this->photos_count !== null, $this->photos_count),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Resolve age visibility based on requesting user context.
     * Admins and the Face owner always see age. Others respect show_age.
     */
    private function resolveAge(Request $request): ?int
    {
        $user = $request->user();

        // Admin always sees age
        if ($user instanceof Admin) {
            return $this->age;
        }

        // Face owner always sees their own age
        if ($user && $user->userable_type === Face::class && $user->userable_id === $this->id) {
            return $this->age;
        }

        // Everyone else (producers) respects show_age
        return $this->show_age ? $this->age : null;
    }
}
