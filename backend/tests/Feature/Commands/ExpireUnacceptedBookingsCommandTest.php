<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\BookingStatus;
use App\Events\BookingExpired;
use App\Models\Booking;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class ExpireUnacceptedBookingsCommandTest extends TestCase
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

    public function test_command_expires_pending_booking_when_shooting_date_has_passed(): void
    {
        Event::fake([BookingExpired::class]);

        $booking = Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->subDay(),
            'date_fin' => now()->subHours(20),
        ]);

        $this->artisan('bookings:expire-unaccepted')
            ->expectsOutput('Found 1 booking(s) to expire.')
            ->expectsOutput("Expired booking #{$booking->id}")
            ->expectsOutput('Done. Expired: 1, Skipped: 0, Failed: 0.')
            ->assertExitCode(Command::SUCCESS);

        $booking->refresh();
        $this->assertSame(BookingStatus::Expired, $booking->status);

        Event::assertDispatched(BookingExpired::class, fn (BookingExpired $event): bool => $event->booking->id === $booking->id);
    }

    public function test_command_does_not_expire_pending_booking_with_future_shooting_date(): void
    {
        Event::fake([BookingExpired::class]);

        $booking = Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->addDay(),
            'date_fin' => now()->addDays(2),
        ]);

        $this->artisan('bookings:expire-unaccepted')
            ->expectsOutput('Found 0 booking(s) to expire.')
            ->expectsOutput('Done. Expired: 0, Skipped: 0, Failed: 0.')
            ->assertExitCode(Command::SUCCESS);

        $booking->refresh();
        $this->assertSame(BookingStatus::Pending, $booking->status);
        Event::assertNotDispatched(BookingExpired::class);
    }

    public function test_command_expires_pending_booking_when_shooting_started_earlier_today(): void
    {
        Event::fake([BookingExpired::class]);

        $booking = Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->subHour(),
            'date_fin' => now()->addHours(3),
        ]);

        $this->artisan('bookings:expire-unaccepted')
            ->expectsOutput('Found 1 booking(s) to expire.')
            ->expectsOutput("Expired booking #{$booking->id}")
            ->expectsOutput('Done. Expired: 1, Skipped: 0, Failed: 0.')
            ->assertExitCode(Command::SUCCESS);

        $booking->refresh();
        $this->assertSame(BookingStatus::Expired, $booking->status);
        Event::assertDispatched(BookingExpired::class, fn (BookingExpired $event): bool => $event->booking->id === $booking->id);
    }

    public function test_command_does_not_expire_non_pending_bookings(): void
    {
        Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->subDay(),
        ]);

        Booking::factory()->paid()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->subDay(),
        ]);

        Booking::factory()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => BookingStatus::Expired,
            'date_debut' => now()->subDay(),
        ]);

        $this->artisan('bookings:expire-unaccepted')
            ->expectsOutput('Found 0 booking(s) to expire.')
            ->expectsOutput('Done. Expired: 0, Skipped: 0, Failed: 0.')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_command_processes_multiple_bookings_in_a_single_run(): void
    {
        Event::fake([BookingExpired::class]);

        $bookings = collect(range(1, 3))->map(function (int $offset): Booking {
            return Booking::factory()->pending()->create([
                'face_id' => $this->faceUser->id,
                'producer_id' => $this->producerUser->id,
                'date_debut' => now()->subDays($offset),
                'date_fin' => now()->subDays($offset)->addHours(4),
            ]);
        });

        $this->artisan('bookings:expire-unaccepted')
            ->expectsOutput('Found 3 booking(s) to expire.')
            ->expectsOutput('Done. Expired: 3, Skipped: 0, Failed: 0.')
            ->assertExitCode(Command::SUCCESS);

        $bookings->each(function (Booking $booking): void {
            $booking->refresh();
            $this->assertSame(BookingStatus::Expired, $booking->status);
        });

        Event::assertDispatchedTimes(BookingExpired::class, 3);
    }

    public function test_command_continues_processing_when_one_expiry_fails_and_reports_summary_counts(): void
    {
        $expiredBooking = Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->subDay(),
        ]);

        $skippedBooking = Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->subDays(2),
        ]);

        $failingBooking = Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->subDays(3),
        ]);

        $this->mock(BookingService::class, function ($mock) use ($expiredBooking, $failingBooking): void {
            $mock->shouldReceive('expireUnaccepted')
                ->times(3)
                ->andReturnUsing(function (Booking $booking) use ($expiredBooking, $failingBooking): void {
                    if ($booking->id === $failingBooking->id) {
                        throw new RuntimeException('boom');
                    }

                    if ($booking->id === $expiredBooking->id) {
                        $booking->update(['status' => BookingStatus::Expired]);
                    }
                });
        });

        $this->artisan('bookings:expire-unaccepted')
            ->expectsOutput('Found 3 booking(s) to expire.')
            ->expectsOutput("Expired booking #{$expiredBooking->id}")
            ->expectsOutput("Skipped booking #{$skippedBooking->id}")
            ->expectsOutputToContain("Failed to expire booking #{$failingBooking->id}: boom")
            ->expectsOutput('Done. Expired: 1, Skipped: 1, Failed: 1.')
            ->assertExitCode(Command::FAILURE);

        $expiredBooking->refresh();
        $skippedBooking->refresh();
        $failingBooking->refresh();

        $this->assertSame(BookingStatus::Expired, $expiredBooking->status);
        $this->assertSame(BookingStatus::Pending, $skippedBooking->status);
        $this->assertSame(BookingStatus::Pending, $failingBooking->status);
    }

    public function test_command_is_idempotent_when_run_multiple_times(): void
    {
        Event::fake([BookingExpired::class]);

        $booking = Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->subDay(),
        ]);

        $this->artisan('bookings:expire-unaccepted')
            ->expectsOutput('Found 1 booking(s) to expire.')
            ->expectsOutput('Done. Expired: 1, Skipped: 0, Failed: 0.')
            ->assertExitCode(Command::SUCCESS);

        $this->artisan('bookings:expire-unaccepted')
            ->expectsOutput('Found 0 booking(s) to expire.')
            ->expectsOutput('Done. Expired: 0, Skipped: 0, Failed: 0.')
            ->assertExitCode(Command::SUCCESS);

        $booking->refresh();
        $this->assertSame(BookingStatus::Expired, $booking->status);
        Event::assertDispatchedTimes(BookingExpired::class, 1);
    }
}
