<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Events\BookingPaid;
use App\Models\Notification;
use Illuminate\Events\Attributes\AsEventListener;
use Illuminate\Support\Facades\Log;

#[AsEventListener(event: BookingPaid::class)]
class NotifyProducerOnBookingPaid
{
    /**
     * Handle the event — notify the Producer that payment is confirmed and chat is unlocked.
     * Non-fatal: booking status is already persisted.
     */
    public function handle(BookingPaid $event): void
    {
        try {
            $booking = $event->booking;

            Notification::create([
                'user_id' => $booking->producer_id,
                'type'    => 'booking_paid',
                'data'    => [
                    'message'    => 'Paiement confirmé ! Le chat est maintenant débloqué.',
                    'booking_id' => $booking->id,
                    'url'        => "/producer/bookings/{$booking->id}",
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('BookingPaid notification failed', [
                'booking_id' => $event->booking->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
