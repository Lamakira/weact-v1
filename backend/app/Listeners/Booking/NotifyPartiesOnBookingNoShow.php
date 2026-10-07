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

        $dueAt = $booking->settlement_due_at?->format('d/m/Y H:i');
        $context = trim((string) $booking->type_contenu) !== ''
            ? trim((string) $booking->type_contenu)
            : "booking #{$booking->id}";

        // Notify Producer
        try {
            Notification::create([
                'user_id' => $booking->producer_id,
                'type' => 'booking_no_show',
                'data' => [
                    'message' => "Absence signalée. Remboursement sur votre wallet le {$dueAt} si la Face ne conteste pas.",
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
                    'message' => "Le Producteur a signalé votre absence pour « {$context} ». Vous pouvez contester jusqu'au {$dueAt}. Sans contestation, il sera remboursé.",
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
