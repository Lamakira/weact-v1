<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Events\BookingAccepted;
use App\Mail\BookingAcceptedMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Canal email ADDITIF sur BookingAccepted : prévient le Producteur que la Face a
 * accepté son booking et qu'il doit payer sous 24h. Pendant symétrique de
 * SendBookingRefusedEmail, qui couvrait déjà le refus.
 *
 * GARDE NON-UGC OBLIGATOIRE : BookingAccepted est partagé cash+UGC
 * (BookingService:250) et SendUgcBookingAcceptedEmail envoie déjà UgcDealAcceptedMail
 * (« expédiez le produit ») sur ce même event. Sans la garde, un deal UGC accepté
 * déclencherait DEUX emails au Producteur. Miroir de SendBookingReceivedEmail:32.
 * L'in-app NotifyProducerOnBookingAccepted reste intouché.
 *
 * NON-FATAL CRITIQUE : BookingAccepted::dispatch() est appelé DANS la transaction
 * de BookingService::accept (:250). Le listener est synchrone ⇒ un throw
 * rollback l'acceptation. Tout le corps est wrappé try/catch SANS re-throw —
 * strict miroir de SendBookingRefusedEmail.
 */
#[AsEventListener(event: BookingAccepted::class)]
final class SendBookingAcceptedEmail
{
    public function handle(BookingAccepted $event): void
    {
        try {
            $booking = $event->booking;

            if ($booking->type_contenu === 'UGC') {
                return; // l'acceptation UGC a son propre email : UgcDealAcceptedMail
            }

            $booking->loadMissing('producer', 'face.userable');

            $producerEmail = trim((string) $booking->producer?->email); // producer_id = users.id
            if ($producerEmail === '') {
                return;
            }

            Mail::to($producerEmail)->queue(new BookingAcceptedMail($booking));
        } catch (\Throwable $e) {
            Log::warning('BookingAcceptedMail queue failed', [
                'booking_id' => $event->booking->id,
                'producer_user_id' => $event->booking->producer_id,
                'error' => $e->getMessage(),
            ]);
            // PAS de re-throw : ne JAMAIS rollback l'acceptation pour un mail.
        }
    }
}
