<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Events\BookingCompleted;
use App\Models\Notification;
use Illuminate\Events\Attributes\AsEventListener;
use Illuminate\Support\Facades\Log;

#[AsEventListener(event: BookingCompleted::class)]
class NotifyPartiesOnBookingCompleted
{
    /**
     * Handle the event — notify Face (wallet credit) and Producer (completion).
     * Each notification is wrapped independently so one failure doesn't skip the other.
     */
    public function handle(BookingCompleted $event): void
    {
        $booking = $event->booking;

        // Notify Face: wallet credited
        try {
            $formattedAmount = number_format((float) ($booking->montant_face_recoit ?? 0), 0, ',', ' ');

            Notification::create([
                'user_id' => $booking->face_id,
                'type'    => 'booking_wallet_credited',
                'data'    => [
                    'message'    => "{$formattedAmount} XOF ont été ajoutés à votre wallet !",
                    'booking_id' => $booking->id,
                    'url'        => "/face/bookings/{$booking->uuid}",
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('BookingCompleted wallet notification for Face failed', [
                'booking_id' => $booking->id,
                'error'      => $e->getMessage(),
            ]);
        }

        // Notify Producer: booking completed
        try {
            Notification::create([
                'user_id' => $booking->producer_id,
                'type'    => 'booking_completed',
                'data'    => [
                    'message'    => 'Votre booking est terminé. Merci pour votre confiance !',
                    'booking_id' => $booking->id,
                    'url'        => "/producer/bookings/{$booking->uuid}",
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('BookingCompleted notification for Producer failed', [
                'booking_id' => $booking->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
