<?php

declare(strict_types=1);

namespace App\Listeners\ImageContract;

use App\Enums\DisputeResolutionOutcome;
use App\Events\BookingDisputeSettled;
use App\Models\ImageContract;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Litige tranché en faveur du Producteur (absence de la Face, annulation tardive) : le booking est
 * mort, l'autorisation d'image est annulée si elle n'avait pas pris effet. Idempotent, non-fatal.
 */
#[AsEventListener(event: BookingDisputeSettled::class)]
final class VoidImageContractOnDisputeForProducer
{
    public function handle(BookingDisputeSettled $event): void
    {
        if ($event->outcome !== DisputeResolutionOutcome::FavorProducer) {
            return;
        }

        try {
            ImageContract::query()
                ->where('booking_id', $event->booking->id)
                ->whereNull('effective_at')
                ->whereNull('voided_at')
                ->update(['voided_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('Image contract void failed (dispute)', [
                'booking_id' => $event->booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
