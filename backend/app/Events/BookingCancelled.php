<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingCancelled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Booking $booking,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("booking.{$this->booking->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'booking.cancelled';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $status = BookingStatus::from((string) $this->booking->getRawOriginal('status'));

        return [
            'booking_id' => $this->booking->id,
            'status' => $status->value,
            'cancelled_by' => $status === BookingStatus::CancelledByFace ? 'face' : 'producer',
            'cancellation_reason' => $this->booking->cancellation_reason,
        ];
    }
}
