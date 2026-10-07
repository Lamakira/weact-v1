<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Admin;
use App\Models\Producer;
use App\Models\User;
use App\Support\Whatsapp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Producer
 */
class ProducerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->currentType();

        return [
            'id' => $this->uuid,
            'slug' => $this->slug,
            'type' => $type !== null ? $type->value : (is_string($this->type) ? $this->type : null),
            'agency_name' => $this->agency_name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'display_name' => $this->display_name,
            'bio' => $this->bio,
            // PII: owner and admin only. The key is omitted (not nulled) for everyone
            // else — this resource is also rendered to Faces (booking/mission payloads)
            // and an off-platform number would defeat the platform.
            ...($this->isPrivilegedViewer($request) ? [
                'whatsapp_number' => $this->whatsapp_number,
                'has_whatsapp' => Whatsapp::isDialable($this->whatsapp_number),
            ] : []),
            'profile_photo_url' => $this->profile_photo_url,
            'thumbnail_url' => $this->thumbnail_url,
            'agency_logo_url' => $this->agency_logo_url,
            'agency_logo_thumbnail_url' => $this->agency_logo_thumbnail_url,
            'average_rating' => $this->average_rating,
            'ratings_count' => $this->ratings_count,
            'missions_count' => $this->missions_count,
            'missions' => MissionSummaryResource::collection($this->whenLoaded('missions')),
            // PII: owner/admin only, and only when the relation is already loaded —
            // never lazy-load the user from a resource (it would run for every viewer).
            ...($this->isPrivilegedViewer($request) && $this->resource->relationLoaded('user') ? [
                'email' => $this->user?->email,
                'is_active' => $this->user?->is_active,
            ] : []),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * True for an admin or for the Producer this resource describes.
     */
    private function isPrivilegedViewer(Request $request): bool
    {
        $viewer = $request->user();

        if ($viewer instanceof Admin) {
            return true;
        }

        return $viewer instanceof User
            && $viewer->userable_type === Producer::class
            && $viewer->userable_id === $this->id;
    }
}
