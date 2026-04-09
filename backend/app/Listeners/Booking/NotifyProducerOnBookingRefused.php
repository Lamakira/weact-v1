<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Events\BookingRefused;
use App\Models\Notification;
use Illuminate\Events\Attributes\AsEventListener;
use Illuminate\Support\Facades\Log;

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
            $faceName = $booking->face?->userable?->display_name ?? 'La Face';

            Notification::create([
                'user_id' => $booking->producer_id,
                'type'    => 'booking_refused',
                'data'    => [
                    'message'    => "{$faceName} a refusé votre booking",
                    'booking_id' => $booking->id,
                    'url'        => "/producer/bookings/{$booking->uuid}",
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('BookingRefused notification failed', [
                'booking_id' => $event->booking->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
