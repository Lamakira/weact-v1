<?php

declare(strict_types=1);

use App\Models\Booking;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Private channel for booking chat.
 *
 * Channel: booking.{bookingId}
 * Authorization: user must be face_id or producer_id on the booking.
 * (face_id and producer_id both reference users.id)
 */
Broadcast::channel('booking.{bookingId}', function ($user, int $bookingId): bool {
    $booking = Booking::find($bookingId);

    if (! $booking) {
        return false;
    }

    return $user->id === $booking->producer_id
        || $user->id === $booking->face_id;
});
