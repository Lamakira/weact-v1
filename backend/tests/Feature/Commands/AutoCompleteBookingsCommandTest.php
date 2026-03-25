<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\BookingStatus;
use App\Events\BookingCompleted;
use App\Models\Booking;
use App\Models\EscrowTransaction;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AutoCompleteBookingsCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private User $faceUser;

    protected function setUp(): void
    {
        parent::setUp();

        $producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
    }

    public function test_command_completes_confirmed_by_producer_booking_past_72h(): void
    {
        Event::fake();

        $booking = Booking::factory()->confirmedByProducer()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_fin' => now()->subHours(73),
        ]);

        EscrowTransaction::factory()->create([
            'booking_id' => $booking->id,
            'amount' => $booking->montant_total_producteur,
            'status' => 'locked',
        ]);

        $this->artisan('bookings:auto-complete')
            ->assertSuccessful();

        $booking->refresh();
        $this->assertEquals(BookingStatus::Completed, $booking->status);

        Event::assertDispatched(BookingCompleted::class, fn ($e) => $e->booking->id === $booking->id);
    }

    public function test_command_completes_confirmed_by_face_booking_past_72h(): void
    {
        Event::fake();

        $booking = Booking::factory()->confirmedByFace()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_fin' => now()->subHours(80),
        ]);

        EscrowTransaction::factory()->create([
            'booking_id' => $booking->id,
            'amount' => $booking->montant_total_producteur,
            'status' => 'locked',
        ]);

        $this->artisan('bookings:auto-complete')
            ->assertSuccessful();

        $booking->refresh();
        $this->assertEquals(BookingStatus::Completed, $booking->status);
    }

    public function test_command_does_not_complete_booking_within_72h(): void
    {
        $booking = Booking::factory()->confirmedByProducer()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_fin' => now()->subHours(48),
        ]);

        $this->artisan('bookings:auto-complete')
            ->assertSuccessful();

        $booking->refresh();
        $this->assertEquals(BookingStatus::ConfirmedByProducer, $booking->status);
    }

    public function test_command_does_not_touch_already_completed_bookings(): void
    {
        Event::fake();

        $booking = Booking::factory()->completed()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_fin' => now()->subHours(100),
        ]);

        $this->artisan('bookings:auto-complete')
            ->assertSuccessful();

        $booking->refresh();
        $this->assertEquals(BookingStatus::Completed, $booking->status);

        Event::assertNotDispatched(BookingCompleted::class);
    }

    public function test_command_does_not_touch_pending_bookings(): void
    {
        $booking = Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_fin' => now()->subHours(100),
        ]);

        $this->artisan('bookings:auto-complete')
            ->assertSuccessful();

        $booking->refresh();
        $this->assertEquals(BookingStatus::Pending, $booking->status);
    }

    public function test_command_is_idempotent(): void
    {
        Event::fake();

        $booking = Booking::factory()->confirmedByProducer()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_fin' => now()->subHours(73),
        ]);

        EscrowTransaction::factory()->create([
            'booking_id' => $booking->id,
            'amount' => $booking->montant_total_producteur,
            'status' => 'locked',
        ]);

        // Run twice
        $this->artisan('bookings:auto-complete')->assertSuccessful();
        $this->artisan('bookings:auto-complete')->assertSuccessful();

        $booking->refresh();
        $this->assertEquals(BookingStatus::Completed, $booking->status);

        // Event dispatched only once (idempotency guard in autoComplete)
        Event::assertDispatchedTimes(BookingCompleted::class, 1);
    }

    public function test_command_processes_multiple_bookings(): void
    {
        Event::fake();

        $bookings = [];
        for ($i = 0; $i < 3; $i++) {
            $booking = Booking::factory()->confirmedByProducer()->create([
                'face_id' => $this->faceUser->id,
                'producer_id' => $this->producerUser->id,
                'date_fin' => now()->subHours(73 + $i),
            ]);

            EscrowTransaction::factory()->create([
                'booking_id' => $booking->id,
                'amount' => $booking->montant_total_producteur,
                'status' => 'locked',
            ]);

            $bookings[] = $booking;
        }

        $this->artisan('bookings:auto-complete')
            ->assertSuccessful();

        foreach ($bookings as $booking) {
            $booking->refresh();
            $this->assertEquals(BookingStatus::Completed, $booking->status);
        }

        Event::assertDispatchedTimes(BookingCompleted::class, 3);
    }
}
