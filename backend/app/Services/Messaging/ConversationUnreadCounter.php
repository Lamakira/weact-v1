<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Models\Booking;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Number of THREADS (not messages) holding at least one unread message for a user :
 * conversations de mission + chats de booking lisibles (BookingPolicy::viewMessages).
 * Une requête agrégée par type de fil, participant uniquement.
 *
 * Same rule as the conversations list: no email-verification condition.
 */
class ConversationUnreadCounter
{
    public function countFor(User $user): int
    {
        return $this->missionThreads($user) + $this->bookingThreads($user);
    }

    private function bookingThreads(User $user): int
    {
        if (! in_array($user->userable_type, [Face::class, Producer::class], true)) {
            return 0;
        }

        return Booking::query()
            ->chatReadableBy($user)
            ->whereHas('messages', fn ($q) => $q->whereNull('read_at')->where('sender_id', '!=', $user->id))
            ->count();
    }

    private function missionThreads(User $user): int
    {
        $query = DB::table('messages')
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->join('candidatures', 'candidatures.id', '=', 'conversations.candidature_id')
            ->whereNull('messages.read_at')
            ->where('messages.sender_id', '!=', $user->id);

        if ($user->userable_type === Face::class) {
            $query->where('candidatures.face_id', $user->userable_id);
        } elseif ($user->userable_type === Producer::class) {
            $query->join('missions', 'missions.id', '=', 'candidatures.mission_id')
                ->where('missions.producer_id', $user->userable_id);
        } else {
            return 0;
        }

        return (int) $query->distinct()->count('messages.conversation_id');
    }
}
