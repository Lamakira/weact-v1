<?php

declare(strict_types=1);

namespace App\Listeners\ImageContract;

use App\Events\BookingCancelled;
use App\Events\BookingExpired;
use App\Events\BookingRefused;
use App\Models\ImageContract;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Annule l'autorisation d'image d'un booking refusé, annulé ou expiré AVANT sa prise d'effet.
 * Une autorisation déjà effective n'est jamais annulée. Idempotent, non-fatal : les événements
 * partent parfois dans la transaction du changement de statut.
 */
#[AsEventListener(event: BookingRefused::class)]
#[AsEventListener(event: BookingCancelled::class)]
#[AsEventListener(event: BookingExpired::class)]
final class VoidImageContractOnBookingEnded
{
    public function handle(BookingRefused|BookingCancelled|BookingExpired $event): void
    {
        try {
            ImageContract::query()
                ->where('booking_id', $event->booking->id)
                ->whereNull('effective_at')
                ->whereNull('voided_at')
                ->update(['voided_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('Image contract void failed', [
                'booking_id' => $event->booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
