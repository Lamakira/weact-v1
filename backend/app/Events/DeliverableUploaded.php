<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Deliverable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Livrable vidéo déposé par la Face (tunnel étape 5, AR8). Dispatché
 * POST-COMMIT par UgcDeliverableService::upload, uniquement sur un
 * upload effectif (un rollback ne notifie pas — D-2.4.f reconduite).
 */
class DeliverableUploaded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Deliverable $deliverable,
    ) {}
}
