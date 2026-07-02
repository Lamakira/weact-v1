<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Events\BookingCommissionPaid;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: BookingCommissionPaid::class)]
class NotifyFaceOnUgcCommissionPaid
{
    /**
     * Notifie la Face que la commission du deal UGC est réglée — elle peut
     * désormais accepter le booking (UGC 2.4, enablement annoncé en 1.5).
     * Non-fatal : le statut commission_paid est déjà persisté.
     * Rappel : bookings.face_id EST le users.id de la Face.
     */
    public function handle(BookingCommissionPaid $event): void
    {
        try {
            $booking = $event->booking;
            $booking->loadMissing('producer.userable');
            $producerName = (string) data_get($booking, 'producer.userable.display_name', 'Le producteur');

            Notification::create([
                'user_id' => $booking->face_id,
                'type' => 'ugc_commission_paid',
                'data' => [
                    'message' => "{$producerName} a réglé la commission WeAct — acceptez le deal UGC pour recevoir le produit.",
                    'booking_id' => $booking->id,
                    'url' => "/face/bookings/{$booking->uuid}",
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('BookingCommissionPaid face notification failed', [
                'booking_id' => $event->booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
