<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Enums\EscrowStatus;
use App\Enums\WalletCreditMotif;
use App\Events\BookingCancelled;
use App\Mail\WalletCreditedMail;
use App\Models\EscrowTransaction;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: BookingCancelled::class)]
final class SendWalletCreditedEmailOnBookingCancelled
{
    /**
     * Non-fatal ABSOLU : tout le corps wrappé dans try/catch pour ne jamais
     * bloquer BookingCancelled::dispatch() côté BookingService::cancel/cancelByFace.
     * Skip silencieux si pas de refund réel (cancel sur Pending/Accepted).
     */
    public function handle(BookingCancelled $event): void
    {
        try {
            $booking = $event->booking;
            $booking->loadMissing(['producer.userable', 'escrowTransaction']);

            /** @var EscrowTransaction|null $escrow */
            $escrow = $booking->escrowTransaction;
            if ($escrow === null || $escrow->status !== EscrowStatus::Refunded->value) {
                return;
            }

            $producer = $booking->producer->userable instanceof Producer
                ? $booking->producer->userable
                : null;
            $producerEmail = trim((string) $booking->producer->email);

            if ($producer === null || $producerEmail === '') {
                return;
            }

            $amount = (int) round($booking->montant_total_producteur * 0.90);
            $newBalance = (int) (User::find($booking->producer_id)->balance ?? 0);

            Mail::to($producerEmail)->queue(
                new WalletCreditedMail(
                    producer: $producer,
                    amount: $amount,
                    motif: WalletCreditMotif::BookingCancellationRefund,
                    newBalance: $newBalance,
                )
            );
        } catch (\Throwable $e) {
            Log::warning('WalletCreditedMail (cancellation) queue failed', [
                'booking_id' => $event->booking->id,
                'producer_user_id' => $event->booking->producer_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
