<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Enums\EscrowStatus;
use App\Events\BookingCompleted;
use App\Mail\WalletCreditedFaceMail;
use App\Models\Face;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Canal email ADDITIF (ugc-7-4) sur BookingCompleted : confirme à la Face que son
 * portefeuille a été crédité (release escrow → montant_face_recoit). Pour TOUS les
 * bookings (cash + UGC, D-7.4.b). L'in-app NotifyPartiesOnBookingCompleted reste intouché.
 *
 * NON-FATAL CRITIQUE (D-7.4.d) : BookingCompleted::dispatch() est appelé DANS la
 * transaction de complétion (BookingService::completeBooking/completeUgcBooking),
 * APRÈS le release escrow. Le listener est synchrone ⇒ un throw rollback le crédit.
 * Tout le corps est wrappé try/catch SANS re-throw — strict miroir de l'in-app.
 */
#[AsEventListener(event: BookingCompleted::class)]
final class SendWalletCreditedFaceEmailOnBookingCompleted
{
    public function handle(BookingCompleted $event): void
    {
        try {
            $booking = $event->booking;

            // Garde RÉPLIQUÉE à l'identique de NotifyPartiesOnBookingCompleted:33-36 (D-7.4.c) :
            // produit-seul UGC (montant=0) et deal suspendu-puis-complété (escrow Refunded →
            // release() no-op) ne donnent AUCUN crédit réel ⇒ pas de mail "X XOF ajoutés".
            $escrowRefunded = $booking->escrowTransaction()
                ->where('status', EscrowStatus::Refunded->value)
                ->exists();
            $creditHappened = (int) ($booking->montant_face_recoit ?? 0) > 0 && ! $escrowRefunded;
            if (! $creditHappened) {
                return;
            }

            $booking->loadMissing('face.userable');
            $face = $booking->face->userable instanceof Face ? $booking->face->userable : null;
            $faceEmail = trim((string) $booking->face->email);
            if ($face === null || $faceEmail === '') {
                return;
            }

            $newBalance = (int) (User::find($booking->face_id)->balance ?? 0);

            Mail::to($faceEmail)->queue(new WalletCreditedFaceMail(
                face: $face,
                amount: (int) $booking->montant_face_recoit,
                newBalance: $newBalance,
            ));
        } catch (\Throwable $e) {
            Log::warning('WalletCreditedFaceMail queue failed', [
                'booking_id' => $event->booking->id,
                'face_user_id' => $event->booking->face_id,
                'error' => $e->getMessage(),
            ]);
            // PAS de re-throw (D-7.4.d) : ne JAMAIS rollback la complétion pour un mail.
        }
    }
}
