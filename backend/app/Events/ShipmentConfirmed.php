<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Shipment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Expédition UGC confirmée (tunnel étape 3, AR8). Dispatché POST-COMMIT
 * par UgcShipmentService, uniquement sur création effective.
 */
class ShipmentConfirmed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Shipment $shipment,
    ) {}
}
