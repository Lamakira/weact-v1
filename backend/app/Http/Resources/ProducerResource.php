<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Producer
 */
class ProducerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User|null $user */
        $user = $this->user;
        $type = $this->currentType();

        return [
            'id' => $this->uuid,
            'slug' => $this->slug,
            'type' => $type !== null ? $type->value : (is_string($this->type) ? $this->type : null),
            'agency_name' => $this->agency_name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'display_name' => $this->display_name,
            'bio' => $this->bio,
            'profile_photo_url' => $this->profile_photo_url,
            'thumbnail_url' => $this->thumbnail_url,
            'agency_logo_url' => $this->agency_logo_url,
            'agency_logo_thumbnail_url' => $this->agency_logo_thumbnail_url,
            'average_rating' => $this->average_rating,
            'ratings_count' => $this->ratings_count,
            'missions_count' => $this->missions_count,
            'missions' => MissionSummaryResource::collection($this->whenLoaded('missions')),
            'email' => $this->whenLoaded('user', fn () => $user?->email),
            'is_active' => $this->whenLoaded('user', fn () => $user?->is_active),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
