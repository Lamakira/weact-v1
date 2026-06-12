<?php

declare(strict_types=1);

namespace App\Http\Resources;

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
        return [
            'id' => $this->uuid,
            'transporteur' => $this->transporteur,
            'numero_suivi' => $this->numero_suivi,
            'note_envoi' => $this->note_envoi,
            'tunnel_status' => $this->tunnel_status->value,
            'tunnel_status_label' => $this->tunnel_status->label(),
            'shipped_at' => $this->shipped_at->toIso8601String(),
            'recu_le' => $this->recu_le?->toIso8601String(),
            'destinataire' => [
                'nom' => $this->destinataire_nom,
                'ville' => $this->destinataire_ville,
                'pays' => $this->destinataire_pays,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
