<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Events\BookingCreated;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: BookingCreated::class)]
class NotifyFaceOnBookingReceived
{
    /**
     * Handle the event — notify the Face of a new booking request.
     * Non-fatal: booking is already persisted. Notification failure only logs a warning.
     */
    public function handle(BookingCreated $event): void
    {
        try {
            $booking = $event->booking;

            $booking->loadMissing('producer.userable');
            $producerName = (string) data_get($booking, 'producer.userable.display_name', 'Le Producteur');
            // A UGC dotation has no shoot date/location → omit them from the message.
            $formattedDate = $booking->date_debut?->format('d/m/Y');
            $dateSuffix = $formattedDate ? " le {$formattedDate}" : '';
            $locationSuffix = $booking->lieu ? " à {$booking->lieu}" : '';

            Notification::create([
                'user_id' => $booking->face_id,
                'type' => 'booking_received',
                'data' => [
                    'message' => "{$producerName} souhaite vous booker pour {$booking->type_contenu}{$dateSuffix}{$locationSuffix}",
                    'booking_id' => $booking->id,
                    'url' => "/face/bookings/{$booking->uuid}",
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('BookingReceived notification failed', [
                'booking_id' => $event->booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
