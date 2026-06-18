<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\UgcSuspension;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Une Face suspendue (suspension douce UGC) vient d'être réactivée (épic 5, story
 * 5.3) — quel que soit le chemin : complétion tardive (Avis validé), appel accepté,
 * ou réactivation admin directe. Dispatché par UgcSuspensionService::reactivate
 * UNIQUEMENT après le commit de la transaction (un rollback tardif ne doit pas
 * avoir notifié).
 *
 * NON ShouldBroadcast : le temps réel passe par la CRÉATION de la Notification
 * (NotificationObserver → infra realtime, calque FaceUgcSuspended).
 */
class FaceUgcReactivated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly UgcSuspension $suspension,
    ) {}
}
