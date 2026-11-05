<?php

declare(strict_types=1);

namespace App\Listeners\ImageContract;

use App\Events\BookingCancelled;
use App\Events\BookingExpired;
use App\Events\BookingRefused;
use App\Events\UgcCommissionRefunded;
use App\Models\ImageContract;
use App\Models\Mission;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Annule l'autorisation d'image d'un booking refusé, annulé, expiré ou remboursé AVANT sa prise
 * d'effet (l'expiration UGC après commission ne passe que par UgcCommissionRefunded), ou celle des
 * candidatures d'une mission dont la commission est remboursée.
 * Une autorisation déjà effective n'est jamais annulée. Idempotent, non-fatal : les événements
 * partent parfois dans la transaction du changement de statut.
 */
#[AsEventListener(event: BookingRefused::class)]
#[AsEventListener(event: BookingCancelled::class)]
#[AsEventListener(event: BookingExpired::class)]
#[AsEventListener(event: UgcCommissionRefunded::class)]
final class VoidImageContractOnBookingEnded
{
    public function handle(BookingRefused|BookingCancelled|BookingExpired|UgcCommissionRefunded $event): void
    {
        $booking = $event instanceof UgcCommissionRefunded ? $event->owner : $event->booking;

        try {
            $query = ImageContract::query();

            if ($booking instanceof Mission) {
                // Commission d'une mission remboursée : les contrats non effectifs de ses candidatures tombent.
                $query->whereIn('candidature_id', $booking->candidatures()->select('id'));
            } else {
                $query->where('booking_id', $booking->id);
            }

            $query->whereNull('effective_at')
                ->whereNull('voided_at')
                ->update(['voided_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('Image contract void failed', [
                'owner_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
