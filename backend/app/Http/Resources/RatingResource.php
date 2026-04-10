<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Rating
 */
class RatingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $rater = $this->rater;
        $raterUserable = $rater->userable;
        $rated = $this->rated;

        return [
            'id' => $this->id,
            'score' => $this->score,
            'comment' => $this->comment,
            'created_at' => $this->created_at?->toISOString(),
            'rater' => [
                'id' => $rater->id,
                'name' => data_get($raterUserable, 'display_name', $rater->email),
                'photo_url' => data_get($raterUserable, 'profile_photo_url'),
            ],
            'rated' => [
                'id' => data_get($rated, 'uuid'),
                'name' => data_get($rated, 'display_name'),
                'photo_url' => data_get($rated, 'profile_photo_url'),
            ],
            'candidature_id' => $this->candidature_id,
        ];
    }
}
