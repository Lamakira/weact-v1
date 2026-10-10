<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Number of CONVERSATIONS (not messages) holding at least one unread message
 * for a user. Single aggregate query, participant conversations only.
 *
 * Same rule as the conversations list: no email-verification condition.
 */
class ConversationUnreadCounter
{
    public function countFor(User $user): int
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
