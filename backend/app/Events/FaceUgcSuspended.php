<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\UgcSuspensionReason;
use App\Models\Shipment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Une Face vient d'être suspendue (suspension douce) sur dépassement de chrono
 * UGC sans livrable validé (épic 5, story 5.1). Dispatché par
 * UgcSuspensionService::suspendForOverdueShipment UNIQUEMENT après le commit de
 * la transaction (un rollback tardif ne doit pas avoir notifié).
 *
 * `faceNewlySuspended` distingue la 1ʳᵉ suspension de la Face (notif Face à
 * envoyer) d'un 2ᵉ deal mort de la même Face déjà suspendue (Producteur notifié,
 * Face non re-notifiée).
 *
 * NON ShouldBroadcast : le temps réel passe par la CRÉATION de la Notification
 * (NotificationObserver → infra realtime, D-4.5.f). Aucun nouvel event broadcast.
 */
class FaceUgcSuspended
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Shipment $shipment,
        public readonly UgcSuspensionReason $reason,
        public readonly bool $faceNewlySuspended,
    ) {}
}
