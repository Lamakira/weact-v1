<?php

declare(strict_types=1);

namespace App\Listeners\ImageContract;

use App\Events\BookingCompleted;
use App\Models\ImageContract;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Prise d'effet de l'autorisation d'image : la Face est payée (booking cash, ou UGC hybride
 * terminé). Idempotent, sans effet sur un booking sans contrat. Non-fatal : la complétion
 * du booking et le versement à la Face sont déjà acquis.
 */
#[AsEventListener(event: BookingCompleted::class)]
final class MarkImageContractEffectiveOnCompleted
{
    public function handle(BookingCompleted $event): void
    {
        try {
            ImageContract::query()
                ->where('booking_id', $event->booking->id)
                ->whereNotNull('face_signed_at')
                ->whereNull('effective_at')
                ->update(['effective_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('Image contract effectiveness failed (completed)', [
                'booking_id' => $event->booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
