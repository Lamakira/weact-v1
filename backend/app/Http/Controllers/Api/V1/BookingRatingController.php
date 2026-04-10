<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Events\BookingRated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\RateBookingRequest;
use App\Http\Resources\BookingRatingResource;
use App\Models\Booking;
use App\Models\BookingRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class BookingRatingController extends Controller
{
    /**
     * Submit a booking rating from one party to the other.
     */
    public function store(RateBookingRequest $request, Booking $booking): JsonResponse
    {
        $user = $request->user();

        if ($this->alreadyRated($booking->id, $user->id)) {
            return $this->alreadyRatedResponse();
        }

        if (Gate::denies('rate', $booking)) {
            if ($booking->status === BookingStatus::Completed) {
                return $this->unauthorizedResponse();
            }

            return $this->ratingNotAllowedResponse();
        }

        if ($this->alreadyRated($booking->id, $user->id)) {
            return $this->alreadyRatedResponse();
        }

        $ratedId = $user->id === $booking->face_id
            ? $booking->producer_id
            : $booking->face_id;

        $rating = BookingRating::create([
            'booking_id' => $booking->id,
            'rater_id' => $user->id,
            'rated_id' => $ratedId,
            'score' => $request->validated('score'),
            'comment' => $request->validated('comment'),
        ]);

        $rating->load(['rater.userable', 'rated.userable', 'booking']);

        BookingRated::dispatch($rating);

        return response()->json([
            'data' => new BookingRatingResource($rating),
            'message' => 'Évaluation envoyée avec succès',
        ], 201);
    }

    /** @phpstan-impure */
    private function alreadyRated(int $bookingId, int $raterId): bool
    {
        return BookingRating::query()
            ->where('booking_id', $bookingId)
            ->where('rater_id', $raterId)
            ->exists();
    }

    private function alreadyRatedResponse(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'ALREADY_RATED',
                'message' => 'Vous avez déjà évalué ce booking',
            ],
        ], 422);
    }

    private function ratingNotAllowedResponse(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'BOOKING_RATING_NOT_ALLOWED',
                'message' => "Les évaluations ne sont possibles qu'après la fin du booking",
            ],
        ], 403);
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => 'Vous n\'êtes pas autorisé à noter ce booking',
            ],
        ], 403);
    }
}
