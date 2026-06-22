<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Events\BookingRefused;
use App\Mail\BookingRefusedMail;
use App\Models\Producer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Canal email ADDITIF (ugc-7-5) sur BookingRefused : prévient le Producteur que la
 * Face a refusé son booking (tous bookings cash + UGC, D-7.5.a). L'in-app
 * NotifyProducerOnBookingRefused reste intouché.
 *
 * NON-FATAL CRITIQUE (D-7.5.a) : BookingRefused::dispatch() est appelé DANS la
 * transaction de BookingService::refuse (:254). Le listener est synchrone ⇒ un throw
 * rollback le refus. Tout le corps est wrappé try/catch SANS re-throw — strict miroir 7-4.
 */
#[AsEventListener(event: BookingRefused::class)]
final class SendBookingRefusedEmail
{
    public function handle(BookingRefused $event): void
    {
        try {
            $booking = $event->booking;
            $booking->loadMissing('producer.userable', 'face.userable');

            $producer = $booking->producer->userable instanceof Producer
                ? $booking->producer->userable
                : null;
            $producerEmail = trim((string) $booking->producer->email);
            if ($producer === null || $producerEmail === '') {
                return;
            }

            Mail::to($producerEmail)->queue(new BookingRefusedMail($booking));
        } catch (\Throwable $e) {
            Log::warning('BookingRefusedMail queue failed', [
                'booking_id' => $event->booking->id,
                'producer_user_id' => $event->booking->producer_id,
                'error' => $e->getMessage(),
            ]);
            // PAS de re-throw (D-7.5.a) : ne JAMAIS rollback le refus pour un mail.
        }
    }
}
