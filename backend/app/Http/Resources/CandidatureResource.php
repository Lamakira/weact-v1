<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Candidature
 */
class CandidatureResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'mission_id' => $this->mission_id,
            'face_id' => $this->face_id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'message_motivation' => $this->message_motivation,
            'shipment' => new ShipmentResource($this->whenLoaded('shipment')),
            'deliverables' => DeliverableResource::collection($this->whenLoaded('deliverables')),
            'mission' => new MissionResource($this->whenLoaded('mission')),
            'face' => new FaceResource($this->whenLoaded('face')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
