<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Events\BookingRated;
use App\Models\Booking;
use App\Models\BookingRating;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: BookingRated::class)]
class NotifyRatedUserOnBookingRating
{
    /**
     * Handle the event — notify the rated user when they receive an evaluation.
     */
    public function handle(BookingRated $event): void
    {
        try {
            /** @var BookingRating $rating */
            $rating = $event->rating;
            $rating->loadMissing('rater.userable', 'booking');

            /** @var Booking $booking */
            $booking = $rating->booking;
            $raterName = (string) data_get($rating, 'rater.userable.display_name', 'Un utilisateur');

            // Determine the URL based on which role was rated
            $isRatedFace = $rating->rated_id === $booking->face_id;
            $url = $isRatedFace
                ? "/face/bookings/{$booking->uuid}"
                : "/producer/bookings/{$booking->uuid}";

            Notification::create([
                'user_id' => $rating->rated_id,
                'type' => 'booking_rating_received',
                'data' => [
                    'message' => "{$raterName} vous a laissé une évaluation de {$rating->score}/5.",
                    'booking_id' => $booking->id,
                    'score' => $rating->score,
                    'url' => $url,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('BookingRated notification failed', [
                'rating_id' => $event->rating->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
