<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Producer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for PUBLIC Mission listing display.
 *
 * This resource exposes only public-safe fields for unauthenticated visitors.
 * Sensitive producer data (email, phone, agency details) are excluded.
 *
 * @mixin \App\Models\Mission
 */
class PublicMissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Producer|null $producer */
        $producer = $this->producer;

        return [
            'id' => $this->uuid,
            'slug' => $this->slug,
            'titre' => $this->titre,
            'description' => $this->description,
            'date_tournage' => $this->date_tournage?->toDateString(),
            'profil_recherche' => $this->profil_recherche,
            'budget' => $this->budget,
            'date_limite_candidature' => $this->date_limite_candidature?->toDateString(),
            'nombre_faces_voulu' => $this->nombre_faces_voulu,
            'type_mission' => $this->type_mission?->value,
            'type_mission_label' => $this->type_mission?->label(),
            'type_mission_autre' => $this->type_mission_autre,
            'genre_voulu' => $this->genre_voulu?->value,
            'genre_voulu_label' => $this->genre_voulu?->label(),
            'lieu' => $this->lieu,
            'duree' => $this->duree,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'created_at' => $this->created_at?->toIso8601String(),
            'producer' => $producer ? [
                'id' => $producer->uuid,
                'slug' => $producer->slug,
                'display_name' => $producer->display_name,
                'profile_photo_thumbnail_url' => $producer->thumbnail_url,
                'average_rating' => data_get($producer, 'ratings_received_avg_score')
                    ? round((float) data_get($producer, 'ratings_received_avg_score'), 1)
                    : null,
                'ratings_count' => (int) data_get($producer, 'ratings_received_count', 0),
            ] : null,
        ];
    }
}
