<?php

declare(strict_types=1);

namespace App\Services\Ugc;

use App\Models\Shipment;
use Illuminate\Support\Carbon;

/**
 * Moteur de deadlines UGC (AR3 — fondation posée en 3.3) : deadlines absolues
 * dérivées de timestamps serveur (NFR3) — le front ne calcule jamais le temps.
 * Périmètre 3.3 : deadline Unboxing dérivée de recu_le, exposée par
 * ShipmentResource (D-3.3.a). La persistance Deliverable.deadline_at (4.1) et
 * le progress/les seuils d'escalade (4.5/5.1) étendront ce service.
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
}
