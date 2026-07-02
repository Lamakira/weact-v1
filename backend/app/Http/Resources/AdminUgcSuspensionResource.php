<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Booking;
use App\Models\Candidature;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Suspension douce UGC vue par l'admin (file de revue des appels + réactivation,
 * story 5.3). Expose la Face en cause + le deal (booking/candidature) + le motif
 * et l'état de l'appel. Calque UgcSuspensionStatusResource::buildDeal (owner via
 * shipment.owner morphTo).
 *
 * @mixin \App\Models\UgcSuspension
 */
class AdminUgcSuspensionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'reason' => $this->reason->value,
            'reason_label' => $this->reason->label(),
            'suspended_at' => $this->suspended_at->toIso8601String(),
            'reactivated_at' => $this->reactivated_at?->toIso8601String(),
            'appeal_status' => $this->appeal_status->value,
            'appeal_status_label' => $this->appeal_status->label(),
            'face' => $this->face === null ? null : [
                'id' => $this->face->id,
                'prenom' => $this->face->prenom,
                'nom' => $this->face->nom,
            ],
            'deal' => $this->buildDeal(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildDeal(): ?array
    {
        $owner = $this->shipment?->owner;

        if ($owner instanceof Booking) {
            return ['owner_kind' => 'booking', 'product_name' => (string) $owner->nom_produit];
        }
        if ($owner instanceof Candidature) {
            $owner->loadMissing('mission');

            return [
                'owner_kind' => 'candidature',
                'product_name' => $owner->mission !== null ? (string) $owner->mission->nom_produit : '',
            ];
        }

        return null;
    }
}
