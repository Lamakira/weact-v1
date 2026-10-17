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
            'display_status' => $this->displayStatus(),
            'display_status_label' => $this->displayStatusLabel(),
            'attendance_dispute' => $this->when(
                $this->relationLoaded('paymentEntry'),
                fn () => $this->attendanceDispute(),
            ),
            // Échéance de confirmation (lot B3) : non-null seulement pour une candidature cash `accepted`
            // non encore confirmée ; null si la mission n'est pas chargée (pas de lazy-load en liste).
            'confirmation_deadline' => $this->relationLoaded('mission') ? $this->confirmationDeadline()?->toIso8601String() : null,
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
