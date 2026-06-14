<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Deliverable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Livrable validé par le Producteur (tunnel étape 5/6, FR7). Dispatché
 * POST-COMMIT par UgcDeliverableService::validate, uniquement sur une validation
 * effective (un rollback ne notifie pas — D-2.4.f reconduite).
 */
class DeliverableValidated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Deliverable $deliverable,
    ) {}
}
