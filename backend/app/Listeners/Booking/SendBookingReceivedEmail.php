<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Events\BookingCreated;
use App\Mail\BookingReceivedMail;
use App\Models\Face;
use App\Models\Producer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: BookingCreated::class)]
final class SendBookingReceivedEmail
{
    /**
     * Handle the event — send a transactional email to the Face notifying the new booking request.
     *
     * Non-fatal ABSOLU : tout le corps du handler est wrappé dans un seul try/catch
     * (strict miroir NotifyFaceOnBookingReceived ligne 21-43). Une exception sur
     * loadMissing/résolution morphTo/Mail::queue ne doit JAMAIS bloquer
     * BookingCreated::dispatch() côté BookingService::create() — sinon le Producer
     * recevrait un 500 alors que le booking est déjà persisté.
     */
    public function handle(BookingCreated $event): void
    {
        try {
            $booking = $event->booking;
            $booking->loadMissing(['face.userable', 'producer.userable']);

            $face = $booking->face->userable instanceof Face ? $booking->face->userable : null;
            $producer = $booking->producer->userable instanceof Producer ? $booking->producer->userable : null;
            $faceEmail = trim((string) $booking->face->email);

            if ($face === null || $producer === null || $faceEmail === '') {
                return;
            }

            Mail::to($faceEmail)->queue(
                new BookingReceivedMail(
                    face: $face,
                    producer: $producer,
                    booking: $booking,
                )
            );
        } catch (\Throwable $e) {
            Log::warning('BookingReceivedMail queue failed', [
                'booking_id' => $event->booking->id,
                'face_user_id' => $event->booking->face_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
