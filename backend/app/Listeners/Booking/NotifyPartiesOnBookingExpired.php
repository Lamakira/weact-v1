<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Events\BookingExpired;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: BookingExpired::class)]
class NotifyPartiesOnBookingExpired
{
    /**
     * Handle the event — notify both parties when a booking expires after 24h without payment.
     * Each notification is wrapped independently so one failure doesn't skip the other.
     */
    public function handle(BookingExpired $event): void
    {
        $booking = $event->booking;
        $booking->loadMissing('face.userable', 'producer.userable');

        // Notify Producer
        try {
            $faceName = (string) data_get($booking, 'face.userable.display_name', 'La Face');

            Notification::create([
                'user_id' => $booking->producer_id,
                'type' => 'booking_expired',
                'data' => [
                    'message' => "Votre booking avec {$faceName} a expiré car {$this->expiryReason($booking)}.",
                    'booking_id' => $booking->id,
                    'url' => "/producer/bookings/{$booking->uuid}",
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('BookingExpired notification for Producer failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Notify Face
        try {
            $producerName = (string) data_get($booking, 'producer.userable.display_name', 'Le Producteur');

            Notification::create([
                'user_id' => $booking->face_id,
                'type' => 'booking_expired',
                'data' => [
                    'message' => "Votre booking avec {$producerName} a expiré car {$this->expiryReason($booking)}.",
                    'booking_id' => $booking->id,
                    'url' => "/face/bookings/{$booking->uuid}",
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('BookingExpired notification for Face failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function expiryReason(\App\Models\Booking $booking): string
    {
        if ($booking->accepted_at === null) {
            return 'la date de tournage est passée sans réponse de la Face';
        }

        return "le paiement n'a pas été effectué dans les 24h";
    }
}
