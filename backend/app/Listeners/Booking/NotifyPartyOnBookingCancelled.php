<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Enums\BookingStatus;
use App\Events\BookingCancelled;
use App\Models\Notification;
use Illuminate\Events\Attributes\AsEventListener;
use Illuminate\Support\Facades\Log;

#[AsEventListener(event: BookingCancelled::class)]
class NotifyPartyOnBookingCancelled
{
    /**
     * Handle the event — notify the non-cancelling party.
     * - Cancelled by Face → notify Producer
     * - Cancelled by Producer → notify Face (with "vous n'êtes pas pénalisé")
     */
    public function handle(BookingCancelled $event): void
    {
        try {
            $booking = $event->booking;
            $booking->loadMissing('face.userable', 'producer.userable');

            $cancelledByFace = $booking->status === BookingStatus::CancelledByFace;

            if ($cancelledByFace) {
                // Notify Producer
                $faceName = $booking->face?->userable?->display_name ?? 'La Face';

                Notification::create([
                    'user_id' => $booking->producer_id,
                    'type'    => 'booking_cancelled',
                    'data'    => [
                        'message'    => "{$faceName} a annulé le booking.",
                        'booking_id' => $booking->id,
                        'url'        => "/producer/bookings/{$booking->id}",
                    ],
                ]);
            } else {
                // Notify Face (cancelled by Producer)
                $producerName = $booking->producer?->userable?->display_name ?? 'Le Producteur';

                Notification::create([
                    'user_id' => $booking->face_id,
                    'type'    => 'booking_cancelled',
                    'data'    => [
                        'message'    => "Votre booking a été annulé par {$producerName}. Vous n'êtes pas pénalisé.",
                        'booking_id' => $booking->id,
                        'url'        => "/face/bookings/{$booking->id}",
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('BookingCancelled notification failed', [
                'booking_id' => $event->booking->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
