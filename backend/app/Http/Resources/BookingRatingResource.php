<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\BookingRating
 */
class BookingRatingResource extends JsonResource
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
            'booking_id' => $this->booking_id,
            'score' => $this->score,
            'comment' => $this->comment,
            'created_at' => $this->created_at?->toIso8601String(),
            'rater' => [
                'id' => $this->rater->id,
                'name' => $this->rater->userable->display_name,
                'photo_url' => $this->rater->userable->profile_photo_url,
            ],
            'rated' => [
                'id' => $this->rated->id,
                'name' => $this->rated->userable->display_name,
                'photo_url' => $this->rated->userable->profile_photo_url,
            ],
        ];
    }
}
