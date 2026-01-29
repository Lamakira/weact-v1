<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for Review display in lists.
 *
 * Transforms Rating model data for public display,
 * including rater information for transparency.
 *
 * @mixin \App\Models\Rating
 */
class ReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get rater's userable (Face or Producer) for display info
        $rater = $this->rater;
        $userable = $rater?->userable;

        return [
            'id' => $this->id,
            'score' => $this->score,
            'comment' => $this->comment,
            'created_at' => $this->created_at?->toIso8601String(),
            'formatted_date' => $this->created_at?->translatedFormat('j F Y'),
            'rater' => [
                'display_name' => $userable?->display_name ?? $rater?->email ?? 'Utilisateur',
                'profile_photo_url' => $userable?->profile_photo_url,
            ],
        ];
    }
}
