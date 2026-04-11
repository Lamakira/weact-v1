<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Events\BookingNoShowReported;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: BookingNoShowReported::class)]
class NotifyPartiesOnBookingNoShow
{
    public function handle(BookingNoShowReported $event): void
    {
        $booking = $event->booking;
        $booking->loadMissing('face.userable', 'producer.userable');

        // Notify Producer
        try {
            $montant = number_format($booking->montant_total_producteur, 0, ',', ' ');

            Notification::create([
                'user_id' => $booking->producer_id,
                'type' => 'booking_no_show',
                'data' => [
                    'message' => "Votre signalement d'absence a été pris en compte. {$montant} XOF ont été crédités dans votre portefeuille.",
                    'booking_id' => $booking->id,
                    'url' => "/producer/bookings/{$booking->uuid}",
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('BookingNoShow producer notification failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Notify Face
        try {
            Notification::create([
                'user_id' => $booking->face_id,
                'type' => 'booking_no_show',
                'data' => [
                    'message' => "Le producteur a signalé votre absence sur le booking #{$booking->id}. Une pénalité a été appliquée à votre profil.",
                    'booking_id' => $booking->id,
                    'url' => "/face/bookings/{$booking->uuid}",
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('BookingNoShow face notification failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
