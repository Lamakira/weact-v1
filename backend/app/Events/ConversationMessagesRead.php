<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class ConversationMessagesRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly string $conversationUuid,
        public readonly string $readerRole,
        public readonly int $readerId,
        public readonly string $readAt,
        public readonly int $lastReadMessageId,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("conversation.{$this->conversationUuid}")];
    }

    public function broadcastAs(): string
    {
        return 'messages.read';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationUuid,
            'reader_role' => $this->readerRole,
            'reader_id' => $this->readerId,
            'read_at' => $this->readAt,
            'last_read_message_id' => $this->lastReadMessageId,
        ];
    }
}
