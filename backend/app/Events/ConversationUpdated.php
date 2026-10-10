<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Lightweight list update, sent on ONE participant's private user channel
 * (unread_count is specific to that recipient).
 */
class ConversationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array<string, mixed>  $latestMessage
     */
    public function __construct(
        public readonly int $recipientId,
        public readonly string $conversationUuid,
        public readonly array $latestMessage,
        public readonly int $unreadCount,
        public readonly string $updatedAt,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("App.Models.User.{$this->recipientId}")];
    }

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationUuid,
            'latest_message' => $this->latestMessage,
            'unread_count' => $this->unreadCount,
            'updated_at' => $this->updatedAt,
        ];
    }
}
