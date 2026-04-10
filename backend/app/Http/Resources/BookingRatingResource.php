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
        $rater = $this->rater;
        $rated = $this->rated;

        return [
            'id' => $this->uuid,
            'booking_id' => $this->booking_id,
            'score' => $this->score,
            'comment' => $this->comment,
            'created_at' => $this->created_at?->toIso8601String(),
            'rater' => [
                'id' => $rater?->id,
                'name' => data_get($rater, 'userable.display_name'),
                'photo_url' => data_get($rater, 'userable.profile_photo_url'),
            ],
            'rated' => [
                'id' => $rated?->id,
                'name' => data_get($rated, 'userable.display_name'),
                'photo_url' => data_get($rated, 'userable.profile_photo_url'),
            ],
        ];
    }
}
