<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Events\BookingRated;
use App\Models\Notification;
use Illuminate\Events\Attributes\AsEventListener;
use Illuminate\Support\Facades\Log;

#[AsEventListener(event: BookingRated::class)]
class NotifyRatedUserOnBookingRating
{
    /**
     * Handle the event — notify the rated user when they receive an evaluation.
     */
    public function handle(BookingRated $event): void
    {
        try {
            $rating = $event->rating;
            $rating->loadMissing('rater.userable', 'booking');

            $booking = $rating->booking;
            $raterName = $rating->rater?->userable?->display_name ?? 'Un utilisateur';

            // Determine the URL based on which role was rated
            $isRatedFace = $rating->rated_id === $booking->face_id;
            $url = $isRatedFace
                ? "/face/bookings/{$booking->uuid}"
                : "/producer/bookings/{$booking->uuid}";

            Notification::create([
                'user_id' => $rating->rated_id,
                'type'    => 'booking_rating_received',
                'data'    => [
                    'message'    => "{$raterName} vous a laissé une évaluation de {$rating->score}/5.",
                    'booking_id' => $booking->id,
                    'score'      => $rating->score,
                    'url'        => $url,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('BookingRated notification failed', [
                'rating_id' => $event->rating->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
