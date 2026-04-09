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
        return [
            'id' => $this->id,
            'score' => $this->score,
            'comment' => $this->comment,
            'created_at' => $this->created_at?->toISOString(),
            'rater' => [
                'id' => $this->rater->id,
                'name' => $this->rater->userable->display_name,
                'photo_url' => $this->rater->userable->profile_photo_url,
            ],
            'rated' => [
                'id' => $this->rated->uuid,
                'name' => $this->rated->display_name,
                'photo_url' => $this->rated->profile_photo_url,
            ],
            'candidature_id' => $this->candidature_id,
        ];
    }
}
