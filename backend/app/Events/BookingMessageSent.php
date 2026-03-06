<?php

declare(strict_types=1);

namespace App\Events;

use App\Http\Resources\BookingMessageResource;
use App\Models\BookingMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly BookingMessage $message,
    ) {}

    /**
     * The private channel for this booking's chat.
     *
     * Channel name: booking.{id} (frontend listens on private-booking.{id})
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("booking.{$this->message->booking_id}"),
        ];
    }

    /**
     * Custom event name. Frontend listens with leading dot: .booking.message.sent
     */
    public function broadcastAs(): string
    {
        return 'booking.message.sent';
    }

    /**
     * Data to broadcast.
     *
     * Note: is_own_message is intentionally omitted — there is no HTTP Request
     * context during broadcast serialization, so the value would always be false.
     * The frontend must determine ownership by comparing sender_id to the current user.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $sender = $this->message->sender;

        return [
            'id'          => $this->message->id,
            'booking_id'  => $this->message->booking_id,
            'sender_id'   => $this->message->sender_id,
            'sender_name' => $sender?->userable?->display_name ?? $sender?->name ?? 'Utilisateur',
            'content'     => $this->message->content,
            'created_at'  => $this->message->created_at->toIso8601String(),
        ];
    }
}
