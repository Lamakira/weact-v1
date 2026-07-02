<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Shipment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Produit reçu par la Face (tunnel étape 4, AR8). Dispatché POST-COMMIT par
 * UgcShipmentService::markReceived, uniquement sur transition effective.
 */
class ProductReceived
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Shipment $shipment,
    ) {}
}
