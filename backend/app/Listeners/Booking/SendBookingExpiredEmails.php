<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Events\BookingExpired;
use App\Mail\BookingExpiredMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Canal e-mail ADDITIF sur BookingExpired : Producteur et Face sont prévenus, texte selon le motif
 * (Face sans réponse / paiement manquant / commission UGC non réglée). L'in-app NotifyPartiesOnBookingExpired
 * reste intouché. BookingExpired est toujours dispatché APRÈS commit ; chaque envoi est isolé et non-fatal.
 *
 * Pas de doublon avec le refus automatique pour dates en conflit : celui-ci émet BookingRefused
 * (BookingRefusedMail au Producteur), jamais BookingExpired.
 */
#[AsEventListener(event: BookingExpired::class)]
final class SendBookingExpiredEmails
{
    public function handle(BookingExpired $event): void
    {
        $booking = $event->booking;

        try {
            $booking->loadMissing('producer.userable', 'face.userable');
            $kind = BookingExpiredMail::kindFor($booking);
        } catch (\Throwable $e) {
            Log::warning('BookingExpired emails: preparation failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        foreach ([true, false] as $forProducer) {
            try {
                if ($forProducer && $event->producerCredited) {
                    continue; // déjà prévenu par WalletCreditedMail (paiement tardif)
                }

                $email = trim((string) ($forProducer ? $booking->producer?->email : $booking->face?->email));
                if ($email === '') {
                    continue;
                }

                Mail::to($email)->queue(new BookingExpiredMail($booking, $kind, $forProducer));
            } catch (\Throwable $e) {
                Log::warning('BookingExpiredMail queue failed', [
                    'booking_id' => $booking->id,
                    'for_producer' => $forProducer,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
