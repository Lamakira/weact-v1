<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Events\BookingCompleted;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: BookingCompleted::class)]
class NotifyPartiesOnBookingCompleted
{
    /**
     * Handle the event — notify Face (wallet credit) and Producer (completion).
     * Each notification is wrapped independently so one failure doesn't skip the other.
     * The Face notification fires only when a credit actually happened
     * (montant_face_recoit > 0) — a product-only UGC booking completes without any
     * wallet movement (RH.3), so "0 XOF ont été ajoutés" must not be sent.
     */
    public function handle(BookingCompleted $event): void
    {
        $booking = $event->booking;

        // Notify Face: wallet credited — only when there IS a credit (RH.3: product-only
        // UGC bookings complete with montant_face_recoit = 0 → no wallet movement, no notif).
        if ((int) ($booking->montant_face_recoit ?? 0) > 0) {
            try {
                $formattedAmount = number_format((float) ($booking->montant_face_recoit ?? 0), 0, ',', ' ');

                Notification::create([
                    'user_id' => $booking->face_id,
                    'type' => 'booking_wallet_credited',
                    'data' => [
                        'message' => "{$formattedAmount} XOF ont été ajoutés à votre wallet !",
                        'booking_id' => $booking->id,
                        'url' => "/face/bookings/{$booking->uuid}",
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::warning('BookingCompleted wallet notification for Face failed', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Notify Producer: booking completed — UNCHANGED (toujours créée).
        try {
            Notification::create([
                'user_id' => $booking->producer_id,
                'type' => 'booking_completed',
                'data' => [
                    'message' => 'Votre booking est terminé. Merci pour votre confiance !',
                    'booking_id' => $booking->id,
                    'url' => "/producer/bookings/{$booking->uuid}",
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('BookingCompleted notification for Producer failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
