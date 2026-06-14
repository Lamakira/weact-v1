<?php

declare(strict_types=1);

namespace App\Services\Ugc;

use App\Enums\DeliverableKind;
use App\Enums\DeliverableValidationStatus;
use App\Models\Deliverable;
use App\Models\Shipment;
use Illuminate\Support\Carbon;

/**
 * Moteur de deadlines UGC (AR3 — fondation posée en 3.3) : deadlines absolues
 * dérivées de timestamps serveur (NFR3) — le front ne calcule jamais le temps.
 * Périmètre 3.3 : deadline Unboxing dérivée de recu_le, exposée par
 * ShipmentResource (D-3.3.a). 4.1 persiste Deliverable.deadline_at à l'upload ;
 * 4.3 ajoute avisDeadlineFor (chrono Avis = validation Unboxing + 14 j). Le
 * progress/les seuils d'escalade (4.5/5.1) étendront ce service.
 */
class UgcDeadlineService
{
    /**
     * Échéance du livrable Unboxing : recu_le + ugc.deliverable_days.unboxing
     * jours. Null tant que la réception n'est pas confirmée.
     */
    public function unboxingDeadlineFor(Shipment $shipment): ?Carbon
    {
        if ($shipment->recu_le === null) {
            return null;
        }

        return $shipment->recu_le->copy()->addDays(
            (int) config('ugc.deliverable_days.unboxing', 7)
        );
    }

    /**
     * Échéance du livrable Avis : validation de l'Unboxing + ugc.deliverable_days.avis
     * jours (D-4.3.e ; architecture-ugc.md:177). Null tant que l'Unboxing n'est pas validé.
     * Dérivable sans ligne Avis (moteur de deadlines 4.5, état avis_pending).
     */
    public function avisDeadlineFor(Shipment $shipment): ?Carbon
    {
        /** @var Deliverable|null $unboxing */
        $unboxing = Deliverable::query()
            ->where('owner_type', $shipment->owner_type)
            ->where('owner_id', $shipment->owner_id)
            ->where('kind', DeliverableKind::Unboxing)
            ->where('validation_status', DeliverableValidationStatus::Validated)
            ->first();

        if ($unboxing === null || $unboxing->validated_at === null) {
            return null;
        }

        return $unboxing->validated_at->copy()->addDays(
            (int) config('ugc.deliverable_days.avis', 14)
        );
    }
}
