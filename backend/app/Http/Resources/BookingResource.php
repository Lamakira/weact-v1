<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\BookingStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

/**
 * @mixin \App\Models\Booking
 */
class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = Auth::user();

        return [
            'id' => $this->uuid,
            'realtime_channel_key' => $this->id,
            'face_id' => $this->face_id,
            'producer_id' => $this->producer_id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'date_debut' => $this->date_debut?->toIso8601String(),
            'date_fin' => $this->date_fin?->toIso8601String(),
            'duree_heures' => $this->duree_heures,
            'type_contenu' => $this->type_contenu,
            'type_compensation' => $this->type_compensation?->value,
            'type_compensation_label' => $this->type_compensation?->label(),
            'nom_produit' => $this->nom_produit,
            'valeur_produit' => $this->valeur_produit,
            'nombre_videos' => $this->nombre_videos,
            'montant_remuneration' => $this->montant_remuneration,
            'commission_ugc' => $this->commission_ugc,
            'lieu' => $this->lieu,
            'message' => $this->message,
            'tarif_base' => $this->tarif_base,
            'montant_total_producteur' => $this->montant_total_producteur,
            'montant_face_recoit' => $this->montant_face_recoit,
            'cancellation_reason' => $this->cancellation_reason,
            'custom_cancellation_reason' => $this->custom_cancellation_reason,
            'fedapay_transaction_id' => $user && $user->id === $this->producer_id ? $this->fedapay_transaction_id : null,
            'payment_mode' => $this->payment_mode,
            'accepted_at' => $this->accepted_at?->toISOString(),
            'shipment' => new ShipmentResource($this->whenLoaded('shipment')),
            'deliverables' => DeliverableResource::collection($this->whenLoaded('deliverables')),
            'face' => new UserResource($this->whenLoaded('face')),
            'producer' => new UserResource($this->whenLoaded('producer')),
            'can_accept' => $user && $user->can('accept', $this->resource),
            'can_refuse' => $user && $user->can('refuse', $this->resource),
            'can_pay' => $user && $user->can('pay', $this->resource),
            // Short-circuit: skip the DB exists() check for non-completed bookings
            // to avoid N+1 on list endpoints.
            'can_rate' => $user && $this->status === BookingStatus::Completed && $user->can('rate', $this->resource),
            'my_rating' => $this->when(
                $this->resource->relationLoaded('raterBookingRating'),
                fn () => $this->raterBookingRating ? new BookingRatingResource($this->raterBookingRating) : null,
                null,
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
