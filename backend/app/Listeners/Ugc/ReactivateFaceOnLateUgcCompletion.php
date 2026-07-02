<?php

declare(strict_types=1);

namespace App\Listeners\Ugc;

use App\Enums\DeliverableKind;
use App\Events\DeliverableValidated;
use App\Models\Shipment;
use App\Models\UgcSuspension;
use App\Services\Ugc\UgcSuspensionService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Dégel à la complétion (épic 5, story 5.3, PO Q1) : quand le Producteur valide
 * l'Avis (deal → Completed), si une suspension active de la Face pointe ce deal
 * (terminer en retard), la lever. Couvre booking ET candidature (validate dispatche
 * DeliverableValidated pour les deux). No-op sur les complétions normales (aucune
 * suspension active liée). No-throw (la complétion est déjà persistée).
 */
#[AsEventListener(event: DeliverableValidated::class)]
class ReactivateFaceOnLateUgcCompletion
{
    public function __construct(
        private readonly UgcSuspensionService $suspensions,
    ) {}

    public function handle(DeliverableValidated $event): void
    {
        try {
            $deliverable = $event->deliverable;

            // Seul l'Avis validé clôt le deal (Unboxing validé → avis_pending, pas Completed).
            if ($deliverable->kind !== DeliverableKind::Avis) {
                return;
            }

            $shipment = Shipment::query()
                ->where('owner_type', $deliverable->owner_type)
                ->where('owner_id', $deliverable->owner_id)
                ->first();

            if ($shipment === null) {
                return;
            }

            $suspension = UgcSuspension::query()
                ->where('shipment_id', $shipment->id)
                ->whereNull('reactivated_at')
                ->first();

            if ($suspension === null) {
                return; // complétion normale (pas une régularisation tardive)
            }

            $this->suspensions->reactivate($suspension);
        } catch (\Throwable $e) {
            Log::warning('UGC late-completion reactivation failed', [
                'deliverable_id' => $event->deliverable->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
