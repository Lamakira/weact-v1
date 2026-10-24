<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Events\BookingRefused;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: BookingRefused::class)]
class NotifyProducerOnBookingRefused
{
    /**
     * Handle the event — notify the Producer that the Face refused the booking.
     * Non-fatal: booking status is already persisted.
     */
    public function handle(BookingRefused $event): void
    {
        try {
            $booking = $event->booking;

            $booking->loadMissing('face.userable');
            $faceName = (string) data_get($booking, 'face.userable.display_name', 'La Face');

            Notification::create([
                'user_id' => $booking->producer_id,
                'type' => 'booking_refused',
                'data' => [
                    'message' => $event->automatic
                        ? "{$faceName} n'est plus disponible sur ces dates : votre demande a été refusée automatiquement."
                        : "{$faceName} a refusé votre booking",
                    'booking_id' => $booking->id,
                    // Refus automatique (Face indisponible) : pas d'alerte WhatsApp « a décliné », qui serait inexacte.
                    'automatic' => $event->automatic,
                    'url' => "/producer/bookings/{$booking->uuid}",
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('BookingRefused notification failed', [
                'booking_id' => $event->booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
