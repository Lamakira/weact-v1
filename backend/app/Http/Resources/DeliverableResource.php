<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Métadonnées d'un livrable vidéo UGC — JAMAIS le video_path brut ni d'URL
 * signée (D-4.1.e : la lecture vidéo Producteur sur disque privé = 4.4).
 *
 * @mixin \App\Models\Deliverable
 */
class DeliverableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'validation_status' => $this->validation_status->value,
            'validation_status_label' => $this->validation_status->label(),
            'chrono_started_at' => $this->chrono_started_at->toIso8601String(),
            'deadline_at' => $this->deadline_at->toIso8601String(),
            'duree_seconds' => $this->duree_seconds,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
