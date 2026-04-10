<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for PUBLIC Article listing display.
 *
 * This resource exposes only public-safe fields for unauthenticated visitors.
 * Excludes: content (heavy, reserved for detail), status (always published),
 * updated_at (admin concern), admin object (email/id sensitive).
 *
 * @mixin \App\Models\Article
 */
class PublicArticleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Admin|null $admin */
        $admin = $this->admin;

        return [
            'id' => $this->uuid,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'category' => [
                'value' => $this->category->value,
                'label' => $this->category->label(),
            ],
            'featured_image' => $this->featured_image_url,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'author_name' => $admin?->name,
        ];
    }
}
