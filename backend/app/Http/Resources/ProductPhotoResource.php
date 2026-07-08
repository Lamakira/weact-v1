<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Photo produit UGC (spec photos produit). Les URLs sont disk-aware (accessors
 * du modèle) : row publique (Mission) → asset direct ; row privée (Booking) →
 * URL signée TTL court (la signature est la garde — cette resource n'est
 * sérialisée que dans des réponses scopées aux deux parties du booking).
 * grid_url/large_url retombent sur l'original tant que le job de variantes
 * n'a pas tourné (jamais de vignette cassée).
 *
 * @mixin \App\Models\ProductPhoto
 */
class ProductPhotoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'position' => $this->position,
            'photo_url' => $this->photo_url,
            'grid_url' => $this->grid_url,
            'large_url' => $this->large_url,
        ];
    }
}
