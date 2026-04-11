<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class NotificationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /**
     * Ensure the event is only broadcast after the database transaction commits.
     * This prevents broadcasting for rolled-back transactions.
     */
    public bool $afterCommit = true;

    public function __construct(
        Notification $notification,
    ) {
        $this->userId = $notification->user_id;
        $this->payload = [
            'id' => $notification->uuid,
            'type' => $notification->type,
            'data' => $notification->data,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }

    public readonly int $userId;

    /**
     * Snapshot the payload at creation time so the queued broadcast matches
     * the created notification even if the model changes later.
     *
     * @var array<string, mixed>
     */
    public readonly array $payload;

    /**
     * Broadcast on the user's private channel.
     *
     * Channel: App.Models.User.{user_id}
     * Already authorized in channels.php.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("App.Models.User.{$this->userId}"),
        ];
    }

    /**
     * Custom event name. Frontend listens with leading dot: .notification.created
     */
    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /**
     * Broadcast payload matching NotificationResource::toArray() shape.
     *
     * The frontend can insert this directly into the notifications store.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
