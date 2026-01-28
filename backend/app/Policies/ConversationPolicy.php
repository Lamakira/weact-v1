<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Conversation;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;

class ConversationPolicy
{
    /**
     * Determine if user can view the conversation.
     *
     * Must be either the Face who applied or the Producer who owns the mission.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        $candidature = $conversation->candidature;

        // Check if user is the Face
        if ($user->userable_type === Face::class) {
            return $candidature->face_id === $user->userable_id;
        }

        // Check if user is the Producer
        if ($user->userable_type === Producer::class) {
            return $candidature->mission->producer_id === $user->userable_id;
        }

        return false;
    }

    /**
     * Determine if user can send messages in the conversation.
     *
     * Must be able to view AND candidature must allow chat access.
     */
    public function sendMessage(User $user, Conversation $conversation): bool
    {
        if (!$this->view($user, $conversation)) {
            return false;
        }

        return $conversation->candidature->canAccessChat();
    }
}
