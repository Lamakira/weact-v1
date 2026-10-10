<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

// Admins and users share numeric ids: every callback must reject a non-User
// principal (e.g. an Admin token) BEFORE comparing ids. The first parameter is
// deliberately untyped: a `User` type-hint would turn an Admin token into a
// TypeError (HTTP 500) instead of a clean 403.
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return $user instanceof User && (int) $user->id === (int) $id;
});

/**
 * Private channel for booking chat.
 *
 * Channel: booking.{bookingId}
 * Authorization: user must be face_id or producer_id on the booking.
 * (face_id and producer_id both reference users.id)
 */
Broadcast::channel('booking.{bookingId}', function ($user, int $bookingId): bool {
    if (! $user instanceof User) {
        return false;
    }

    $booking = Booking::find($bookingId);

    if (! $booking) {
        return false;
    }

    return $user->id === $booking->producer_id
        || $user->id === $booking->face_id;
});

/**
 * Private channel for Face <-> Producer conversations.
 *
 * Channel: conversation.{uuid}
 * Authorization: only the two participants (ConversationPolicy::view), User
 * principals only (admin tokens refused), lookup by uuid.
 */
Broadcast::channel('conversation.{uuid}', function ($user, string $uuid): bool {
    if (! $user instanceof User) {
        return false;
    }

    $conversation = Conversation::where('uuid', $uuid)->first();

    return $conversation !== null && Gate::forUser($user)->allows('view', $conversation);
});
