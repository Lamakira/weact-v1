<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Events\BookingAccepted;
use App\Models\Notification;
use Illuminate\Events\Attributes\AsEventListener;
use Illuminate\Support\Facades\Log;

#[AsEventListener(event: BookingAccepted::class)]
class NotifyProducerOnBookingAccepted
{
    /**
     * Handle the event — notify the Producer that the Face accepted the booking.
     * Non-fatal: booking status is already persisted.
     */
    public function handle(BookingAccepted $event): void
    {
        try {
            $booking = $event->booking;

            $booking->loadMissing('face.userable');
            $faceName = $booking->face?->userable?->display_name ?? 'La Face';

            Notification::create([
                'user_id' => $booking->producer_id,
                'type'    => 'booking_accepted',
                'data'    => [
                    'message'    => "{$faceName} a accepté votre booking",
                    'booking_id' => $booking->id,
                    'url'        => "/producer/bookings/{$booking->uuid}",
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('BookingAccepted notification failed', [
                'booking_id' => $event->booking->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
