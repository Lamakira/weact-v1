<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingRating;
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
     * Only the Face can refuse, and only when status is pending.
     */
    public function refuse(User $user, Booking $booking): bool
    {
        return $user->id === $booking->face_id
            && $booking->status === BookingStatus::Pending;
    }

    /**
     * Determine if the user can cancel the booking.
     * Only the Producer can cancel, and only when status is pending, accepted, or paid.
     */
    public function cancel(User $user, Booking $booking): bool
    {
        $cancellableStatuses = [
            BookingStatus::Pending,
            BookingStatus::Accepted,
            BookingStatus::Paid,
        ];

        return $user->id === $booking->producer_id
            && in_array($booking->status, $cancellableStatuses, true);
    }

    /**
     * Determine if the Face can cancel the booking.
     * Face can cancel only accepted or paid bookings.
     */
    public function cancelByFace(User $user, Booking $booking): bool
    {
        $cancellableStatuses = [
            BookingStatus::Accepted,
            BookingStatus::Paid,
        ];

        return $user->id === $booking->face_id
            && in_array($booking->status, $cancellableStatuses, true);
    }

    /**
     * Determine if the user can pay for the booking.
     * Only the Producer can pay, and only when status is accepted.
     */
    public function pay(User $user, Booking $booking): bool
    {
        return $user->id === $booking->producer_id
            && $booking->status === BookingStatus::Accepted
            && $booking->type_contenu !== 'UGC';
    }

    /**
     * Determine if the user can pay the WeAct commission of a UGC booking.
     * Only the owning Producer, on a pending UGC booking (dedicated UGC path).
     */
    public function payCommission(User $user, Booking $booking): bool
    {
        return $user->id === $booking->producer_id
            && $booking->type_contenu === 'UGC'
            && $booking->status === BookingStatus::Pending;
    }

    /**
     * Determine if the user can report a Face no-show.
     * Only the Producer can report, and only when status is paid.
     */
    public function reportNoShow(User $user, Booking $booking): bool
    {
        return $user->id === $booking->producer_id;
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
            BookingStatus::ConfirmedByFace,
            BookingStatus::ConfirmedByProducer,
        ];

        return $isParty && in_array($booking->status, $confirmableStatuses, true);
    }

    /**
     * Determine if the user can rate the booking counterparty.
     * User must be booking party, booking must be completed, and user has not rated yet.
     */
    public function rate(User $user, Booking $booking): bool
    {
        $isParty = $user->id === $booking->face_id || $user->id === $booking->producer_id;

        if (! $isParty) {
            return false;
        }

        if ($booking->status !== BookingStatus::Completed) {
            return false;
        }

        return ! BookingRating::query()
            ->where('booking_id', $booking->id)
            ->where('rater_id', $user->id)
            ->exists();
    }

    /**
     * Determine if the user can view booking chat messages.
     * Both parties can read messages when booking is paid or beyond.
     * Completed bookings still allow reading history.
     */
    public function viewMessages(User $user, Booking $booking): bool
    {
        $isParty = $user->id === $booking->face_id || $user->id === $booking->producer_id;

        $chatEligibleStatuses = [
            BookingStatus::Paid,
            BookingStatus::ConfirmedByFace,
            BookingStatus::ConfirmedByProducer,
            BookingStatus::Completed,
        ];

        return $isParty && in_array($booking->status, $chatEligibleStatuses, true);
    }

    /**
     * Determine if the user can send a booking chat message.
     * Both parties can send messages only while the booking is active (not completed/cancelled).
     */
    public function sendMessage(User $user, Booking $booking): bool
    {
        $isParty = $user->id === $booking->face_id || $user->id === $booking->producer_id;

        $chatActiveStatuses = [
            BookingStatus::Paid,
            BookingStatus::ConfirmedByFace,
            BookingStatus::ConfirmedByProducer,
        ];

        return $isParty && in_array($booking->status, $chatActiveStatuses, true);
    }
}
