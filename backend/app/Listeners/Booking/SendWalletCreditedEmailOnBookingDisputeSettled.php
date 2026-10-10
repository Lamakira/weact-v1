<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Enums\BookingStatus;
use App\Enums\DisputeResolutionOutcome;
use App\Enums\WalletCreditMotif;
use App\Events\BookingDisputeSettled;
use App\Mail\WalletCreditedFaceMail;
use App\Mail\WalletCreditedMail;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Email « wallet crédité » au règlement d'un litige booking :
 * - favor_producer : le Producteur est remboursé (motif absence Face ou annulation tardive) ;
 * - favor_face sur annulation Producteur tardive : la Face est payée (pas de BookingCompleted
 *   dans ce cas). Pour favor_face sur absence, BookingCompleted porte déjà l'email Face.
 *
 * Non-fatal ABSOLU : tout le corps est wrappé try/catch sans re-throw.
 */
#[AsEventListener(event: BookingDisputeSettled::class)]
final class SendWalletCreditedEmailOnBookingDisputeSettled
{
    public function handle(BookingDisputeSettled $event): void
    {
        try {
            $booking = $event->booking;

            if ($event->creditedAmount <= 0) {
                return;
            }

            if ($event->outcome === DisputeResolutionOutcome::FavorProducer) {
                $this->sendToProducer($event);

                return;
            }

            if ($booking->status === BookingStatus::CancelledByProducer) {
                $this->sendToFace($event);
            }
        } catch (\Throwable $e) {
            Log::warning('WalletCredited mail (booking dispute settled) queue failed', [
                'booking_id' => $event->booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendToProducer(BookingDisputeSettled $event): void
    {
        $booking = $event->booking;
        $booking->loadMissing('producer.userable');

        $producer = $booking->producer->userable instanceof Producer
            ? $booking->producer->userable
            : null;
        $producerEmail = trim((string) $booking->producer->email);

        if ($producer === null || $producerEmail === '') {
            return;
        }

        $motif = $booking->status === BookingStatus::NoShow
            ? WalletCreditMotif::BookingNoShowRefund
            : WalletCreditMotif::BookingCancellationRefund;

        $newBalance = (int) (User::find($booking->producer_id)->balance ?? 0);

        Mail::to($producerEmail)->queue(new WalletCreditedMail(
            producer: $producer,
            amount: $event->creditedAmount,
            motif: $motif,
            newBalance: $newBalance,
        ));
    }

    private function sendToFace(BookingDisputeSettled $event): void
    {
        $booking = $event->booking;
        $booking->loadMissing('face.userable');

        $face = $booking->face->userable instanceof Face ? $booking->face->userable : null;
        $faceEmail = trim((string) $booking->face->email);

        if ($face === null || $faceEmail === '') {
            return;
        }

        $newBalance = (int) (User::find($booking->face_id)->balance ?? 0);

        Mail::to($faceEmail)->queue(new WalletCreditedFaceMail(
            face: $face,
            amount: $event->creditedAmount,
            newBalance: $newBalance,
        ));
    }
}
