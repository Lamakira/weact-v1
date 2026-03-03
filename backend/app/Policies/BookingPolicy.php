<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Producer;
use App\Models\User;

class BookingPolicy
{
    /**
     * Determine if the user can list their own bookings.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

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

    /**
     * Determine if the user can accept the booking.
     * Only the Face can accept, and only when status is pending.
     */
    public function accept(User $user, Booking $booking): bool
    {
        return $user->id === $booking->face_id
            && $booking->status === BookingStatus::Pending;
    }

    /**
     * Determine if the user can refuse the booking.
     * Only the Face can refuse, and only when status is pending or paid.
     */
    public function refuse(User $user, Booking $booking): bool
    {
        return $user->id === $booking->face_id
            && in_array($booking->status, [BookingStatus::Pending, BookingStatus::Paid], true);
    }

    /**
     * Determine if the user can pay for the booking.
     * Only the Producer can pay, and only when status is accepted.
     */
    public function pay(User $user, Booking $booking): bool
    {
        return $user->id === $booking->producer_id
            && $booking->status === BookingStatus::Accepted;
    }

    /**
     * Determine if the user can confirm the booking completion.
     * Either the Face or the Producer can confirm when in a confirmable status.
     */
    public function confirm(User $user, Booking $booking): bool
    {
        $isParty = $user->id === $booking->face_id || $user->id === $booking->producer_id;

        $confirmableStatuses = [
            BookingStatus::Paid,
            BookingStatus::InProgress,
            BookingStatus::ConfirmedByFace,
            BookingStatus::ConfirmedByProducer,
        ];

        return $isParty && in_array($booking->status, $confirmableStatuses, true);
    }
}
