<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Booking;
use App\Models\Producer;
use App\Models\User;

class BookingPolicy
{
    /**
     * Determine if the user can create a booking.
     */
    public function create(User $user): bool
    {
        return $user->userable_type === Producer::class;
    }

    /**
     * Determine if the user can view the booking.
     * Both the Producer (creator) and the Face (target) can view.
     */
    public function view(User $user, Booking $booking): bool
    {
        return $user->id === $booking->producer_id
            || $user->id === $booking->face_id;
    }
}
