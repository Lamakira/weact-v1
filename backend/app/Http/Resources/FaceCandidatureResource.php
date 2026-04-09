<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Resource for Face's candidature list view.
 *
 * Returns candidature with nested mission and producer summary data.
 * Motivation message is truncated for list display.
 */
class FaceCandidatureResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'message_motivation' => $this->message_motivation
                ? Str::limit($this->message_motivation, 100)
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'mission' => $this->whenLoaded('mission', fn () => [
                'id' => $this->mission->uuid,
                'titre' => $this->mission->titre,
                'date_tournage' => $this->mission->date_tournage?->format('Y-m-d'),
                'lieu' => $this->mission->lieu,
                'budget' => $this->mission->budget,
            ]),
            'producer' => $this->when(
                $this->relationLoaded('mission') && $this->mission?->relationLoaded('producer') && $this->mission?->producer,
                fn () => [
                    'id' => $this->mission->producer->uuid,
                    'display_name' => $this->mission->producer->display_name,
                    'type' => $this->mission->producer->type?->value,
                    'profile_photo_url' => $this->mission->producer->profile_photo_url,
                ]
            ),
            'conversation_id' => $this->whenLoaded('conversation', fn () => $this->conversation?->uuid),
        ];
    }
}
