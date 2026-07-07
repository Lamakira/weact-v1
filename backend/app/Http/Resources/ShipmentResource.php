<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\Ugc\UgcDeadlineService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Shipment
 */
class ShipmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\Shipment $shipment */
        $shipment = $this->resource;

        return [
            'id' => $this->uuid,
            'transporteur' => $this->transporteur,
            'numero_suivi' => $this->numero_suivi,
            'note_envoi' => $this->note_envoi,
            'tunnel_status' => $this->tunnel_status->value,
            'tunnel_status_label' => $this->tunnel_status->label(),
            'shipped_at' => $this->shipped_at->toIso8601String(),
            'recu_le' => $this->recu_le?->toIso8601String(),
            // Échéance Unboxing DÉRIVÉE de recu_le (D-3.3.a, NFR3) — null avant réception.
            'unboxing_deadline_at' => app(UgcDeadlineService::class)
                ->unboxingDeadlineFor($shipment)
                ?->toIso8601String(),
            // Échéance Avis DÉRIVÉE (validated_at Unboxing + config, NFR3, D-4.6.a) —
            // null hors avis_pending (tant qu'aucun Unboxing n'est validé).
            'avis_deadline_at' => app(UgcDeadlineService::class)
                ->avisDeadlineFor($shipment)
                ?->toIso8601String(),
            'destinataire' => [
                'nom' => $this->destinataire_nom,
                'ville' => $this->destinataire_ville,
                'pays' => $this->destinataire_pays,
            ],
            // Preuve « produit reçu » (spec réception) : photos privées, URLs
            // signées, visibles des deux parties. Absente (whenLoaded) pour les
            // shipments pré-deploy ou non eager-loadés — aucune section vide.
            'reception_photos' => ProductPhotoResource::collection($this->whenLoaded('receptionPhotos')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
