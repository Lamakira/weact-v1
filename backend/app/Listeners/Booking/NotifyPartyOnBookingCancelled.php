<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Enums\BookingStatus;
use App\Events\BookingCancelled;
use App\Mail\BookingCancelledMail;
use App\Models\Notification;
use Illuminate\Events\Attributes\AsEventListener;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

#[AsEventListener(event: BookingCancelled::class)]
class NotifyPartyOnBookingCancelled
{
    /**
     * Handle the event — notify the non-cancelling party.
     * - Cancelled by Face → notify Producer
     * - Cancelled by Producer → notify Face (with "vous n'êtes pas pénalisé")
     */
    public function handle(BookingCancelled $event): void
    {
        $booking = $event->booking;
        $booking->loadMissing('face.userable', 'producer.userable');

        $cancelledByFace = $booking->status === BookingStatus::CancelledByFace;

        if ($cancelledByFace) {
            $faceName = $booking->face?->userable?->display_name ?? 'La Face';
            $recipientUserId = $booking->producer_id;
            $notificationData = [
                'message'    => "{$faceName} a annulé le booking.",
                'booking_id' => $booking->id,
                'url'        => "/producer/bookings/{$booking->id}",
            ];
        } else {
            $producerName = $booking->producer?->userable?->display_name ?? 'Le Producteur';
            $recipientUserId = $booking->face_id;
            $notificationData = [
                'message'    => "Votre booking a été annulé par {$producerName}. Vous n'êtes pas pénalisé.",
                'booking_id' => $booking->id,
                'url'        => "/face/bookings/{$booking->id}",
            ];
        }

        try {
            Notification::create([
                'user_id' => $recipientUserId,
                'type'    => 'booking_cancelled',
                'data'    => $notificationData,
            ]);
        } catch (\Throwable $e) {
            Log::warning('BookingCancelled notification record failed', [
                'booking_id' => $booking->id,
                'error'      => $e->getMessage(),
            ]);
        }

        try {
            $recipientUser = \App\Models\User::find($recipientUserId);
            if ($recipientUser?->email) {
                Mail::to($recipientUser->email)->queue(
                    new BookingCancelledMail($booking, $cancelledByFace ? 'face' : 'producer')
                );
            }
        } catch (\Throwable $e) {
            Log::warning('BookingCancelled email queue failed', [
                'booking_id' => $booking->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
