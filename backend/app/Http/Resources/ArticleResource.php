<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->content,
            'excerpt' => $this->excerpt,
            'category' => [
                'value' => $this->category?->value,
                'label' => $this->category?->label(),
            ],
            'status' => [
                'value' => $this->status?->value,
                'label' => $this->status?->label(),
            ],
            'featured_image' => $this->featured_image_url,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'admin' => new AdminResource($this->whenLoaded('admin')),
        ];
    }
}
