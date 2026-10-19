<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\FaceEntitlementService;
use App\Support\PublicViewer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for PUBLIC Face listing display.
 *
 * This resource exposes only public-safe fields for unauthenticated visitors.
 * Sensitive data like tariffs, bio, etc. are excluded.
 *
 * @mixin \App\Models\Face
 */
class PublicFaceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewerIsProducer = PublicViewer::isProducer($request);

        return [
            'id' => $this->uuid,
            'username' => $this->username,
            'prenom' => $this->prenom,
            // Guests get the initial only ("K."); a logged-in Producer gets the full last
            // name (lot B13, PublicViewer — the public cache keys on the viewer class).
            'nom' => $viewerIsProducer ? $this->nom : $this->lastNameInitial(),
            'display_name' => $viewerIsProducer ? $this->display_name : $this->publicDisplayName(),
            'ville' => $this->ville,
            'categories' => $this->categoriesWithLabels(),
            'is_available' => $this->is_available,
            'profile_photo_url' => $this->profile_photo_url,
            'profile_photo_medium_url' => $this->medium_url,
            'profile_photo_grid_url' => $this->grid_url,
            'profile_photo_large_url' => $this->large_url,
            'profile_photo_thumbnail_url' => $this->thumbnail_url,
            'average_rating' => $this->average_rating,
            'has_elite_badge' => app(FaceEntitlementService::class)
                ->capabilities($this->resource)
                ->hasEliteBadge,
        ];
    }
}
