<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Resource for Producer's candidature list view.
 *
 * Returns candidature with nested face summary data.
 * Motivation message is truncated for list display (150 chars).
 */
class ProducerCandidatureResource extends JsonResource
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
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'message_motivation' => $this->message_motivation
                ? Str::limit($this->message_motivation, 150)
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'conversation_id' => $this->whenLoaded('conversation', fn () => $this->conversation?->id),
            'face' => $this->whenLoaded('face', fn () => [
                'id' => $this->face->id,
                'display_name' => trim($this->face->prenom . ' ' . $this->face->nom),
                'profile_photo_url' => $this->face->profile_photo_url,
                'category' => $this->face->categorie?->value,
                'city' => $this->face->ville,
                'tarif_horaire' => $this->face->tarif_horaire,
                'tarif_journalier' => $this->face->tarif_journalier,
            ]),
        ];
    }
}
