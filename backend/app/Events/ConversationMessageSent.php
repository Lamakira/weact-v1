<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Message $message,
        public readonly string $conversationUuid,
    ) {}

    /**
     * Channel name: conversation.{uuid} (frontend listens on private-conversation.{uuid}).
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("conversation.{$this->conversationUuid}")];
    }

    /**
     * Frontend listens with leading dot: .message.sent
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Same shape as MessageResource for the recipient. is_own_message is omitted
     * (no request context here): the client compares sender_id to the current user.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $senderName = data_get($this->message->sender, 'userable.display_name');

        return [
            'id' => $this->message->id,
            'conversation_id' => $this->conversationUuid,
            'content' => $this->message->content,
            'sender_id' => $this->message->sender_id,
            'sender_type' => $this->message->sender_type,
            'sender_name' => is_string($senderName) && $senderName !== '' ? $senderName : 'Utilisateur',
            'read_at' => $this->message->read_at?->toIso8601String(),
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }
}
