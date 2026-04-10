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
 *
 * @mixin \App\Models\Candidature
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
        $mission = $this->mission;
        $producer = $mission?->producer;

        return [
            'id' => $this->uuid,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'message_motivation' => $this->message_motivation
                ? Str::limit($this->message_motivation, 100)
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'mission' => $this->whenLoaded('mission', fn () => [
                'id' => $mission?->uuid,
                'titre' => $mission?->titre,
                'date_tournage' => $mission?->date_tournage?->format('Y-m-d'),
                'lieu' => $mission?->lieu,
                'budget' => $mission?->budget,
            ]),
            'producer' => $this->when(
                $mission !== null && $mission->relationLoaded('producer') && $producer !== null,
                fn () => [
                    'id' => $producer?->uuid,
                    'display_name' => $producer?->display_name,
                    'type' => $producer?->currentType()?->value,
                    'profile_photo_url' => $producer?->profile_photo_url,
                ]
            ),
            'conversation_id' => $this->whenLoaded('conversation', fn () => $this->conversation?->uuid),
        ];
    }
}
