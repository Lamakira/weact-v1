<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Enums\BookingStatus;
use App\Enums\DisputeResolutionOutcome;
use App\Events\BookingDisputeSettled;
use App\Mail\BookingDisputeSettledMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * E-mail d'issue au règlement d'un litige booking : CHAQUE partie reçoit exactement UN e-mail.
 * La partie réellement créditée a déjà son e-mail « wallet crédité » (qui porte une phrase sur
 * l'issue : SendWalletCreditedEmailOnBookingDisputeSettled, ou SendWalletCreditedFaceEmailOnBookingCompleted
 * pour la Face sur une absence) ; l'autre partie reçoit ici l'e-mail d'issue. Une gagnante sans crédit
 * (montant nul) n'a pas d'e-mail de crédit : elle reçoit l'e-mail d'issue.
 *
 * Non-fatal ABSOLU : chaque envoi est isolé, sans re-throw.
 */
#[AsEventListener(event: BookingDisputeSettled::class)]
final class SendBookingDisputeSettledEmail
{
    public function handle(BookingDisputeSettled $event): void
    {
        $credited = $event->creditedAmount > 0;
        $favorProducer = $event->outcome === DisputeResolutionOutcome::FavorProducer;

        if (! ($favorProducer && $credited)) {
            $this->send($event, forProducer: true);
        }

        if (! (! $favorProducer && $credited)) {
            $this->send($event, forProducer: false);
        }
    }

    private function send(BookingDisputeSettled $event, bool $forProducer): void
    {
        try {
            $booking = $event->booking;
            $booking->loadMissing('producer.userable', 'face.userable');

            $email = trim((string) ($forProducer ? $booking->producer?->email : $booking->face?->email));
            if ($email === '') {
                return;
            }

            Mail::to($email)->queue(new BookingDisputeSettledMail(
                booking: $booking,
                forProducer: $forProducer,
                outcome: $event->outcome,
                contested: $booking->disputed_at !== null,
                // Seul le chemin favor_producer distingue absence / annulation (le statut NoShow est conservé).
                penalty: $event->outcome === DisputeResolutionOutcome::FavorProducer
                    && $booking->status === BookingStatus::NoShow,
            ));
        } catch (\Throwable $e) {
            Log::warning('BookingDisputeSettledMail queue failed', [
                'booking_id' => $event->booking->id,
                'for_producer' => $forProducer,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
