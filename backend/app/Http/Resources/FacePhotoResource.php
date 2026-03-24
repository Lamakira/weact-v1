<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FacePhotoResource extends JsonResource
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
            'photo_url' => $this->photo_url,
            'medium_url' => $this->medium_url,
            'thumbnail_url' => $this->thumbnail_url,
            'position' => $this->position,
        ];
    }
}
