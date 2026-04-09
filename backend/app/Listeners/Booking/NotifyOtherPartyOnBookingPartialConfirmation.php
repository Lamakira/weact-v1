<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Events\BookingPartiallyConfirmed;
use App\Models\Notification;
use Illuminate\Events\Attributes\AsEventListener;
use Illuminate\Support\Facades\Log;

#[AsEventListener(event: BookingPartiallyConfirmed::class)]
class NotifyOtherPartyOnBookingPartialConfirmation
{
    public function handle(BookingPartiallyConfirmed $event): void
    {
        try {
            $booking = $event->booking;
            $isFace = $event->confirmer->id === $booking->face_id;

            Notification::create([
                'user_id' => $isFace ? $booking->producer_id : $booking->face_id,
                'type' => 'booking_confirmation_pending',
                'data' => [
                    'message' => $isFace
                        ? 'La Face a confirmé. À votre tour !'
                        : 'Le producteur a confirmé. À votre tour !',
                    'booking_id' => $booking->id,
                    'url' => $isFace
                        ? "/producer/bookings/{$booking->uuid}"
                        : "/face/bookings/{$booking->uuid}",
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Partial confirmation notification failed', [
                'booking_id' => $event->booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
