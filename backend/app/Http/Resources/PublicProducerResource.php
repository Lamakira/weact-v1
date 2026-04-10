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
        $type = $this->currentType();

        return [
            'id' => $this->uuid,
            'slug' => $this->slug,
            'type' => $type !== null ? $type->value : (is_string($this->type) ? $this->type : null),
            'display_name' => $this->display_name,
            'agency_name' => $this->agency_name,
            'bio' => $this->bio,
            'profile_photo_url' => $this->profile_photo_url,
            'thumbnail_url' => $this->thumbnail_url,
            'agency_logo_url' => $this->agency_logo_url,
            'agency_logo_thumbnail_url' => $this->agency_logo_thumbnail_url,
            // missions_count uses real data from database (Story 5-11)
            'missions_count' => $this->published_missions_count,
            // Rating data from Producer model (Story 8-5)
            'average_rating' => $this->average_rating,
            'ratings_count' => $this->ratings_count,
            // French formatted date: "janvier 2026"
            'member_since' => $this->created_at?->translatedFormat('F Y'),
        ];
    }
}
