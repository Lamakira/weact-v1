<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Events\BookingAccepted;
use App\Listeners\Ugc\Concerns\ResolvesUgcOwnerRecipients;
use App\Mail\UgcDealAcceptedMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Canal email ADDITIF (ugc-7-3) sur BookingAccepted : prévient le Producteur qu'il
 * doit EXPÉDIER le produit. BookingAccepted est partagé cash+UGC (BookingService:218)
 * ⇒ garde type_contenu='UGC' OBLIGATOIRE (D-7.3.a). L'in-app NotifyProducerOnBookingAccepted
 * reste intouché. Non-fatal (dispatch post-commit, statut déjà persisté).
 */
#[AsEventListener(event: BookingAccepted::class)]
final class SendUgcBookingAcceptedEmail
{
    use ResolvesUgcOwnerRecipients;

    public function handle(BookingAccepted $event): void
    {
        try {
            $booking = $event->booking;

            if ($booking->type_contenu !== 'UGC') {
                return; // event partagé cash+UGC : l'email « expédiez » ne vaut que pour l'UGC (D-7.3.a)
            }

            $email = $this->producerEmailFor($booking);
            if ($email === '') {
                return;
            }

            Mail::to($email)->queue(new UgcDealAcceptedMail(
                producerName: $this->producerNameFor($booking),
                faceName: $this->faceNameFor($booking),
                productName: $this->productNameFor($booking),
                dealUrl: $this->producerDealUrlFor($booking),
            ));
        } catch (\Throwable $e) {
            Log::warning('UgcDealAcceptedMail (booking) queue failed', [
                'booking_id' => $event->booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
