<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for PUBLIC Producer profile display.
 *
 * This resource is separate from ProducerResource to allow different
 * fields for public vs authenticated views.
 *
 * @mixin \App\Models\Producer
 */
class PublicProducerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Note: Carbon locale is set globally in AppServiceProvider

        return [
            'id' => $this->id,
            'type' => $this->type?->value ?? $this->type,
            'display_name' => $this->display_name,
            'agency_name' => $this->agency_name,
            'bio' => $this->bio,
            'profile_photo_url' => $this->profile_photo_url,
            'thumbnail_url' => $this->thumbnail_url,
            'agency_logo_url' => $this->agency_logo_url,
            'agency_logo_thumbnail_url' => $this->agency_logo_thumbnail_url,
            // MVP placeholders - will be updated when Epic 5 (Missions) and Epic 8 (Ratings) are implemented
            'missions_count' => 0,
            'average_rating' => null,
            'ratings_count' => 0,
            // French formatted date: "janvier 2026"
            'member_since' => $this->created_at?->translatedFormat('F Y'),
        ];
    }
}
