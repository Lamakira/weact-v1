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
     * Handle the event — notify both parties that payment is confirmed and chat is unlocked.
     * Each notification is wrapped independently so one failure doesn't skip the other.
     */
    public function handle(BookingPaid $event): void
    {
        $booking = $event->booking;

        // Notify Producer: payment confirmation
        try {
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
            Log::warning('BookingPaid notification for Producer failed', [
                'booking_id' => $booking->id,
                'error'      => $e->getMessage(),
            ]);
        }

        // Notify Face: producer has paid, chat is now unlocked
        try {
            Notification::create([
                'user_id' => $booking->face_id,
                'type'    => 'booking_paid',
                'data'    => [
                    'message'    => 'Le producteur a effectué le paiement. Le chat est maintenant débloqué !',
                    'booking_id' => $booking->id,
                    'url'        => "/face/bookings/{$booking->id}",
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('BookingPaid notification for Face failed', [
                'booking_id' => $booking->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
