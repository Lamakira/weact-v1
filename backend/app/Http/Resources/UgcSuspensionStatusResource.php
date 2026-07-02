<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\UgcSuspensionReason;
use App\Models\Booking;
use App\Services\Ugc\UgcDeadlineService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * État de suspension UGC d'une Face (écran 10A, story 5.2). Le contrôleur n'instancie
 * cette Resource que pour une suspension ACTIVE (reactivated_at IS NULL).
 *
 * @mixin \App\Models\UgcSuspension
 */
class UgcSuspensionStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reason' => $this->reason->value,
            'reason_label' => $this->reason->label(),
            'suspended_at' => $this->suspended_at->toIso8601String(),
            'reactivation_deadline' => $this->suspended_at
                ->copy()
                ->addDays((int) config('ugc.late_completion_days', 30))
                ->toIso8601String(),
            'appeal_status' => $this->appeal_status->value,
            'deal' => $this->buildDeal(),
        ];
    }

    /**
     * Contexte du deal en cause : null si shipment supprimé (shipment_id nullOnDelete)
     * ou owner introuvable. owner_uuid = cible de routage front (Booking ou Mission).
     *
     * @return array<string, mixed>|null
     */
    private function buildDeal(): ?array
    {
        $shipment = $this->shipment;
        $owner = $shipment?->owner;

        if ($shipment === null || $owner === null) {
            return null;
        }

        $deadline = $this->reason === UgcSuspensionReason::UnboxingDeadlineMissed
            ? app(UgcDeadlineService::class)->unboxingDeadlineFor($shipment)
            : app(UgcDeadlineService::class)->avisDeadlineFor($shipment);

        if ($owner instanceof Booking) {
            return [
                'owner_kind' => 'booking',
                'owner_uuid' => $owner->uuid,
                'product_name' => (string) $owner->nom_produit,
                'missed_deadline_at' => $deadline?->toIso8601String(),
            ];
        }

        // Candidature : la cible de routage front est la Mission (owner_uuid).
        $owner->loadMissing('mission');
        $mission = $owner->mission;

        return [
            'owner_kind' => 'candidature',
            'owner_uuid' => $mission?->uuid,
            'product_name' => $mission !== null ? (string) $mission->nom_produit : '',
            'missed_deadline_at' => $deadline?->toIso8601String(),
        ];
    }
}
