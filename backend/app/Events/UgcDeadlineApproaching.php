<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Shipment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Un chrono d'upload livrable ACTIF (la Face doit encore uploader) franchit un
 * palier d'escalade (4.5). Dispatché par ProcessUgcDeadlinesCommand UNIQUEMENT
 * après l'avancée idempotente de shipments.last_notified_threshold (1 event = 1
 * palier nouvellement atteint, D-4.5.d).
 *
 * NON ShouldBroadcast : le temps réel passe par la CRÉATION de la Notification
 * (NotificationObserver → NotificationCreated), infra realtime-notifications
 * réutilisée (D-4.5.f). Aucun nouvel event broadcast.
 */
class UgcDeadlineApproaching
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Shipment $shipment,
        public readonly int $level,
    ) {}
}
