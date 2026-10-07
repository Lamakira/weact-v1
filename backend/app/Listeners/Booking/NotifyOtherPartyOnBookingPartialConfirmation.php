<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Events\BookingPartiallyConfirmed;
use App\Models\Booking;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: BookingPartiallyConfirmed::class)]
class NotifyOtherPartyOnBookingPartialConfirmation
{
    public function handle(BookingPartiallyConfirmed $event): void
    {
        try {
            $booking = $event->booking;
            $isFace = $event->confirmer->id === $booking->face_id;

            $producerMessage = 'La Face a confirmé. À votre tour !';
            $dueAt = $booking->faceConfirmedAutoCompleteDueAt();
            if ($dueAt !== null) {
                // Le paiement automatique de la Face est daté : le Producteur sait jusqu'à quand signaler une absence.
                $producerMessage = 'La Face a confirmé la prestation. Si elle n\'est pas venue, signalez son absence avant le '
                    .Booking::formatForBusiness($dueAt).' ; sinon elle sera payée automatiquement.';
            }

            Notification::create([
                'user_id' => $isFace ? $booking->producer_id : $booking->face_id,
                'type' => 'booking_confirmation_pending',
                'data' => [
                    'message' => $isFace
                        ? $producerMessage
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
