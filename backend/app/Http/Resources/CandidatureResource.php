<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\ImageContractPayload;
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
            // Date-limite de reconfirmation UGC (source unique : config ugc.reconfirm_window_hours).
            'reconfirm_deadline' => $this->reconfirmDeadline()?->toIso8601String(),
            // Net reçu par la Face une fois sa place payée (entrée d'escrow) ; null avant sélection payée.
            'montant_face_recoit' => $this->when(
                $this->relationLoaded('paymentEntry'),
                fn () => $this->paymentEntry?->montant_face_recoit !== null ? (int) $this->paymentEntry->montant_face_recoit : null,
            ),
            'conversation_id' => $this->whenLoaded('conversation', fn () => $this->conversation?->uuid),
            // Notation côté Face (lot C8) : clés présentes seulement si `ratings` est préchargée.
            'can_rate' => $this->when(
                $this->relationLoaded('ratings'),
                fn () => $this->raterRatingPayload($request->user()?->id)['can_rate'],
            ),
            'my_rating' => $this->when(
                $this->relationLoaded('ratings'),
                fn () => $this->raterRatingPayload($request->user()?->id)['my_rating'],
            ),
            'message_motivation' => $this->message_motivation,
            'image_contract' => $this->imageContractPayload(),
            // La Face doit-elle signer pour confirmer ? Clé absente si la fonctionnalité est éteinte ou si
            // les relations ne sont pas chargées (pas de lazy-load en liste).
            'must_sign_image_contract' => $this->when(
                $this->mustSignImageContract() !== null,
                fn () => $this->mustSignImageContract(),
            ),
            'shipment' => new ShipmentResource($this->whenLoaded('shipment')),
            'deliverables' => DeliverableResource::collection($this->whenLoaded('deliverables')),
            'mission' => new MissionResource($this->whenLoaded('mission')),
            'face' => new FaceResource($this->whenLoaded('face')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Null si la fonctionnalité est éteinte, si la relation n'est pas chargée (pas de N+1 en liste)
     * ou si la Face n'a pas (encore) signé.
     *
     * @return array<string, mixed>|null
     */
    private function imageContractPayload(): ?array
    {
        if (! config('image_contracts.enabled') || ! $this->resource->relationLoaded('imageContract')) {
            return null;
        }

        $contract = $this->resource->imageContract;

        return $contract === null ? null : ImageContractPayload::for($contract);
    }
}
