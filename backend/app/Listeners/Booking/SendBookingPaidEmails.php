<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Events\BookingPaid;
use App\Mail\BookingPaidFaceMail;
use App\Mail\BookingPaidProducerMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Canal e-mail ADDITIF sur BookingPaid (booking cash payé) : Face (paiement sécurisé, net, chat ouvert)
 * et Producteur (paiement reçu). L'in-app NotifyPartiesOnBookingPaid reste intouché.
 *
 * BookingPaid est dispatché DANS la transaction de BookingService::markAsPaid (listener synchrone) :
 * les mails sont mis en file `afterCommit` et tout est non-fatal, chaque envoi isolé. Le chemin
 * « paiement tardif crédité au wallet » ne dispatche pas cet événement : il garde son seul WalletCreditedMail.
 * UGC : jamais ici (markAsPaid est un no-op UGC), voir SendBookingCommissionPaidProducerEmail.
 */
#[AsEventListener(event: BookingPaid::class)]
final class SendBookingPaidEmails
{
    public function handle(BookingPaid $event): void
    {
        $booking = $event->booking;

        try {
            $booking->loadMissing('producer.userable', 'face.userable');
        } catch (\Throwable $e) {
            Log::warning('BookingPaid emails: relations load failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        try {
            $faceEmail = trim((string) $booking->face?->email);
            if ($faceEmail !== '') {
                Mail::to($faceEmail)->queue((new BookingPaidFaceMail($booking))->afterCommit());
            }
        } catch (\Throwable $e) {
            Log::warning('BookingPaidFaceMail queue failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $producerEmail = trim((string) $booking->producer?->email);
            if ($producerEmail !== '') {
                Mail::to($producerEmail)->queue((new BookingPaidProducerMail($booking))->afterCommit());
            }
        } catch (\Throwable $e) {
            Log::warning('BookingPaidProducerMail queue failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
