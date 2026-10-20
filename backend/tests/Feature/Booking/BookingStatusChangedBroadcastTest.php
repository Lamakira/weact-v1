<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Events\BookingAccepted;
use App\Events\BookingCommissionPaid;
use App\Events\BookingCompleted;
use App\Events\BookingPaid;
use App\Events\BookingRefused;
use App\Events\BookingStatusChanged;
use App\Models\Booking;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BookingStatusChangedBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private User $faceUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => Producer::factory()->create()->id,
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => Face::factory()->create(['is_available' => true])->id,
        ]);
    }

    private function booking(string $state): Booking
    {
        return Booking::factory()->{$state}()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);
    }

    public function test_event_broadcasts_on_the_private_booking_channel_with_status(): void
    {
        $booking = $this->booking('accepted');

        $event = new BookingStatusChanged($booking);

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame("private-booking.{$booking->id}", $channels[0]->name);
        $this->assertSame('booking.status_changed', $event->broadcastAs());
        $this->assertSame(
            ['booking_id' => $booking->id, 'status' => BookingStatus::Accepted->value],
            $event->broadcastWith(),
        );
    }

    public function test_accepting_a_booking_broadcasts_the_new_status(): void
    {
        Event::fake([BookingStatusChanged::class]);
        $booking = $this->booking('pending');

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/accept")
            ->assertOk();

        Event::assertDispatched(
            BookingStatusChanged::class,
            fn (BookingStatusChanged $e): bool => $e->booking->id === $booking->id
                && $e->status === BookingStatus::Accepted->value,
        );
    }

    public function test_refusing_a_booking_broadcasts_the_new_status(): void
    {
        Event::fake([BookingStatusChanged::class]);
        $booking = $this->booking('pending');

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/refuse")
            ->assertOk();

        Event::assertDispatched(
            BookingStatusChanged::class,
            fn (BookingStatusChanged $e): bool => $e->booking->id === $booking->id
                && $e->status === BookingStatus::Refused->value,
        );
    }

    public function test_payment_completion_commission_and_refusal_events_are_relayed(): void
    {
        Event::fake([BookingStatusChanged::class]);
        $booking = $this->booking('paid');

        BookingPaid::dispatch($booking);
        BookingCompleted::dispatch($booking);
        BookingCommissionPaid::dispatch($booking);
        BookingAccepted::dispatch($booking);
        BookingRefused::dispatch($booking, true);

        Event::assertDispatchedTimes(BookingStatusChanged::class, 5);
    }
}
