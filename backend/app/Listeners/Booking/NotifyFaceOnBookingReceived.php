<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Events\BookingCreated;
use App\Models\Notification;
use Illuminate\Events\Attributes\AsEventListener;
use Illuminate\Support\Facades\Log;

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
            $producerName = $booking->producer?->userable?->display_name ?? 'Le Producteur';
            $formattedDate = $booking->date_debut?->format('d/m/Y') ?? '';

            Notification::create([
                'user_id' => $booking->face_id,
                'type'    => 'booking_received',
                'data'    => [
                    'message'    => "{$producerName} souhaite vous booker pour {$booking->type_contenu} le {$formattedDate}",
                    'booking_id' => $booking->id,
                    'url'        => "/face/bookings/{$booking->id}",
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('BookingReceived notification failed', [
                'booking_id' => $event->booking->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
