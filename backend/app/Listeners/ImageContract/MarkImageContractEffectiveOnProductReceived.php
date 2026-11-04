<?php

declare(strict_types=1);

namespace App\Listeners\ImageContract;

use App\Enums\CompensationType;
use App\Events\ProductReceived;
use App\Models\Booking;
use App\Models\ImageContract;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Prise d'effet d'un booking UGC « produit seul » : la contrepartie est le produit, l'autorisation
 * démarre à sa réception. Ignore l'expédition d'une candidature (incrément missions) et un
 * hybride (la Face y est payée en argent : prise d'effet à la complétion). Idempotent, non-fatal.
 */
#[AsEventListener(event: ProductReceived::class)]
final class MarkImageContractEffectiveOnProductReceived
{
    public function handle(ProductReceived $event): void
    {
        try {
            $owner = $event->shipment->owner;

            if (! $owner instanceof Booking || $owner->type_compensation !== CompensationType::Product) {
                return;
            }

            ImageContract::query()
                ->where('booking_id', $owner->id)
                ->whereNotNull('face_signed_at')
                ->whereNull('effective_at')
                ->update(['effective_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('Image contract effectiveness failed (product received)', [
                'shipment_id' => $event->shipment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
