<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Enums\WalletCreditMotif;
use App\Events\BookingNoShowReported;
use App\Mail\WalletCreditedMail;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: BookingNoShowReported::class)]
final class SendWalletCreditedEmailOnBookingNoShow
{
    /**
     * Non-fatal ABSOLU : tout le corps wrappé dans try/catch pour ne jamais
     * bloquer BookingNoShowReported::dispatch() côté BookingService::reportNoShow.
     * Pour no-show, le crédit a forcément eu lieu sinon reportNoShow aurait throw
     * avant le dispatch — pas besoin de check escrow status.
     */
    public function handle(BookingNoShowReported $event): void
    {
        try {
            $booking = $event->booking;
            $booking->loadMissing('producer.userable');

            $producer = $booking->producer->userable instanceof Producer
                ? $booking->producer->userable
                : null;
            $producerEmail = trim((string) $booking->producer->email);

            if ($producer === null || $producerEmail === '') {
                return;
            }

            $amount = (int) $booking->montant_total_producteur;
            $newBalance = (int) (User::find($booking->producer_id)->balance ?? 0);

            Mail::to($producerEmail)->queue(
                new WalletCreditedMail(
                    producer: $producer,
                    amount: $amount,
                    motif: WalletCreditMotif::BookingNoShowRefund,
                    newBalance: $newBalance,
                )
            );
        } catch (\Throwable $e) {
            Log::warning('WalletCreditedMail (no-show) queue failed', [
                'booking_id' => $event->booking->id,
                'producer_user_id' => $event->booking->producer_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
