<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Resource for Face's candidature list view.
 *
 * Returns candidature with nested mission and producer summary data.
 * Motivation message is truncated for list display.
 *
 * @mixin \App\Models\Candidature
 */
class FaceCandidatureResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $mission = $this->mission;
        $producer = $mission?->producer;

        return [
            'id' => $this->uuid,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            // Statut effectif d'affichage (pending + mission plus publiée => mission_closed).
            'display_status' => $this->displayStatus(),
            'display_status_label' => $this->displayStatusLabel(),
            'message_motivation' => $this->message_motivation
                ? Str::limit($this->message_motivation, 100)
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
            // Date-limite de reconfirmation (9-2, D-9.2.a) : source unique = config
            // ugc.reconfirm_window_hours (jamais dupliquée côté front). Non-null
            // seulement pour une candidature UGC acceptée non encore reconfirmée.
            'reconfirm_deadline' => $this->reconfirmDeadline()?->toIso8601String(),
            // Échéance de confirmation (lot B3) : non-null seulement pour une candidature cash `accepted`
            // non encore confirmée ; null si la mission n'est pas chargée (pas de lazy-load en liste).
            'confirmation_deadline' => $this->relationLoaded('mission') ? $this->confirmationDeadline()?->toIso8601String() : null,
            // Absence mission contestable (fenêtre 72 h) — clé omise si paymentEntry non chargée.
            'attendance_dispute' => $this->when(
                $this->relationLoaded('paymentEntry'),
                fn () => $this->attendanceDispute(),
            ),
            'mission' => $this->whenLoaded('mission', fn () => [
                'id' => $mission?->uuid,
                'titre' => $mission?->titre,
                'date_tournage' => $mission?->date_tournage?->format('Y-m-d'),
                'lieu' => $mission?->lieu,
                'budget' => $mission?->budget,
                'type_compensation' => $mission?->type_compensation?->value,
            ]),
            'producer' => $this->when(
                $mission !== null && $mission->relationLoaded('producer') && $producer !== null,
                fn () => [
                    'id' => $producer?->uuid,
                    'display_name' => $producer?->display_name,
                    'type' => $producer?->currentType()?->value,
                    'profile_photo_url' => $producer?->profile_photo_url,
                    'profile_photo_thumbnail_url' => $producer?->thumbnail_url,
                ]
            ),
            'conversation_id' => $this->whenLoaded('conversation', fn () => $this->conversation?->uuid),
            'shipment' => new ShipmentResource($this->whenLoaded('shipment')),
            'deliverables' => DeliverableResource::collection($this->whenLoaded('deliverables')),
        ];
    }
}
