<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Events\BookingAccepted;
use App\Events\BookingCommissionPaid;
use App\Events\BookingCompleted;
use App\Events\BookingDisputeSettled;
use App\Events\BookingPaid;
use App\Events\BookingPartiallyConfirmed;
use App\Events\BookingRefused;
use App\Events\BookingStatusChanged;
use Illuminate\Support\Facades\Log;

/**
 * Relaie les événements métier de transition vers le canal temps réel du booking.
 * Jamais bloquant : un échec de diffusion ne doit pas faire échouer la transition.
 */
class BroadcastBookingStatusChange
{
    public function handle(
        BookingAccepted|BookingRefused|BookingPaid|BookingPartiallyConfirmed|BookingCompleted|BookingCommissionPaid|BookingDisputeSettled $event,
    ): void {
        try {
            BookingStatusChanged::dispatch($event->booking);
        } catch (\Throwable $e) {
            Log::warning('BookingStatusChanged broadcast failed', [
                'booking_id' => $event->booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
