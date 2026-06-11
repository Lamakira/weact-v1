<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

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

class BookingExpiryTest extends TestCase
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

    public function test_command_expires_accepted_booking_older_than_24h(): void
    {
        Event::fake([BookingExpired::class]);

        $booking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'accepted_at' => now()->subHours(25),
        ]);

        $this->artisan('bookings:expire-unpaid')
            ->expectsOutput('Found 1 booking(s) to expire.')
            ->expectsOutput("Expired booking #{$booking->id}")
            ->expectsOutput('Done. Expired: 1, Failed: 0.')
            ->assertExitCode(Command::SUCCESS);

        $booking->refresh();
        $this->assertEquals(BookingStatus::Expired, $booking->status);

        Event::assertDispatched(BookingExpired::class, fn (BookingExpired $event): bool => $event->booking->id === $booking->id);
    }

    public function test_command_skips_accepted_ugc_booking_older_than_24h(): void
    {
        // UGC 2.4 : aucun paiement cash après acceptation — le cron expire
        // ne doit pas tuer le tunnel UGC 24h après l'acceptation.
        Event::fake([BookingExpired::class]);

        $booking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'accepted_at' => now()->subHours(25),
            'type_contenu' => 'UGC',
            'tarif_base' => 0,
            'type_compensation' => 'product',
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'commission_ugc' => 2500,
        ]);

        $this->artisan('bookings:expire-unpaid')
            ->expectsOutput('Found 0 booking(s) to expire.')
            ->expectsOutput('Done. Expired: 0, Failed: 0.')
            ->assertExitCode(Command::SUCCESS);

        $booking->refresh();
        $this->assertEquals(BookingStatus::Accepted, $booking->status);
        Event::assertNotDispatched(BookingExpired::class);
    }

    public function test_command_still_expires_cash_booking_with_lowercase_ugc_type(): void
    {
        // type_contenu est un champ libre : « ugc » minuscule n'est PAS un booking UGC
        // (le PHP compare `=== 'UGC'`) — la collation _ci ne doit pas l'exclure du cron (BINARY).
        Event::fake([BookingExpired::class]);

        $booking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'accepted_at' => now()->subHours(25),
            'type_contenu' => 'ugc',
        ]);

        $this->artisan('bookings:expire-unpaid')
            ->expectsOutput('Found 1 booking(s) to expire.')
            ->assertExitCode(Command::SUCCESS);

        $this->assertEquals(BookingStatus::Expired, $booking->fresh()->status);
    }

    public function test_command_skips_accepted_booking_younger_than_24h(): void
    {
        Event::fake([BookingExpired::class]);

        $booking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'accepted_at' => now()->subHours(12),
        ]);

        $this->artisan('bookings:expire-unpaid')
            ->expectsOutput('Found 0 booking(s) to expire.')
            ->expectsOutput('Done. Expired: 0, Failed: 0.')
            ->assertExitCode(Command::SUCCESS);

        $booking->refresh();
        $this->assertEquals(BookingStatus::Accepted, $booking->status);
        Event::assertNotDispatched(BookingExpired::class);
    }

    public function test_command_skips_non_accepted_bookings(): void
    {
        Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        Booking::factory()->paid()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        Booking::factory()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => BookingStatus::Expired,
            'accepted_at' => now()->subHours(40),
        ]);

        $this->artisan('bookings:expire-unpaid')
            ->expectsOutput('Found 0 booking(s) to expire.')
            ->expectsOutput('Done. Expired: 0, Failed: 0.')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_command_continues_processing_when_one_expiry_fails(): void
    {
        $failingBooking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'accepted_at' => now()->subHours(26),
        ]);

        $successfulBooking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'accepted_at' => now()->subHours(28),
        ]);

        $this->mock(BookingService::class, function ($mock) use ($failingBooking): void {
            $mock->shouldReceive('expire')
                ->twice()
                ->andReturnUsing(function (Booking $booking) use ($failingBooking): void {
                    if ($booking->id === $failingBooking->id) {
                        throw new RuntimeException('boom');
                    }

                    $booking->update(['status' => BookingStatus::Expired]);
                });
        });

        $this->artisan('bookings:expire-unpaid')
            ->expectsOutput('Found 2 booking(s) to expire.')
            ->expectsOutputToContain("Failed to expire booking #{$failingBooking->id}: boom")
            ->expectsOutput('Done. Expired: 1, Failed: 1.')
            ->assertExitCode(Command::SUCCESS);

        $successfulBooking->refresh();
        $this->assertEquals(BookingStatus::Expired, $successfulBooking->status);
    }

    public function test_booking_service_expire_is_idempotent_for_non_accepted_booking(): void
    {
        Event::fake([BookingExpired::class]);

        $booking = Booking::factory()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => BookingStatus::Expired,
            'accepted_at' => now()->subHours(30),
        ]);

        app(BookingService::class)->expire($booking);

        $booking->refresh();
        $this->assertEquals(BookingStatus::Expired, $booking->status);
        Event::assertNotDispatched(BookingExpired::class);
    }

    public function test_accept_sets_accepted_at_timestamp(): void
    {
        $booking = Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::Accepted->value);

        $booking->refresh();
        $this->assertNotNull($booking->accepted_at);
    }

    public function test_command_warns_and_skips_accepted_booking_with_null_accepted_at(): void
    {
        Event::fake([BookingExpired::class]);

        // Booking in accepted status with no accepted_at (pre-migration data)
        Booking::factory()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => BookingStatus::Accepted,
            'accepted_at' => null,
            'updated_at' => now()->subHours(48), // old enough to expire
        ]);

        $this->artisan('bookings:expire-unpaid')
            ->expectsOutput('Found 0 booking(s) to expire.')
            ->expectsOutputToContain('have no accepted_at timestamp and will not be expired')
            ->expectsOutput('Done. Expired: 0, Failed: 0.')
            ->assertExitCode(Command::SUCCESS);

        Event::assertNotDispatched(BookingExpired::class);
    }

    public function test_booking_expired_event_is_dispatched_when_service_expires_booking(): void
    {
        Event::fake([BookingExpired::class]);

        $booking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'accepted_at' => now()->subHours(30),
        ]);

        app(BookingService::class)->expire($booking);

        $booking->refresh();
        $this->assertEquals(BookingStatus::Expired, $booking->status);

        Event::assertDispatched(BookingExpired::class, fn (BookingExpired $event): bool => $event->booking->id === $booking->id);
    }

    public function test_booking_service_expire_unaccepted_expires_pending_booking(): void
    {
        Event::fake([BookingExpired::class]);

        $booking = Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->subDay(),
            'accepted_at' => now()->subDays(2),
        ]);

        app(BookingService::class)->expireUnaccepted($booking);

        $booking->refresh();
        $this->assertSame(BookingStatus::Expired, $booking->status);
        $this->assertNull($booking->accepted_at);

        Event::assertDispatched(BookingExpired::class, fn (BookingExpired $event): bool => $event->booking->id === $booking->id);
    }

    public function test_booking_service_expire_unaccepted_is_idempotent_for_non_pending_booking(): void
    {
        Event::fake([BookingExpired::class]);

        $booking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->subDay(),
        ]);

        app(BookingService::class)->expireUnaccepted($booking);

        $booking->refresh();
        $this->assertSame(BookingStatus::Accepted, $booking->status);
        Event::assertNotDispatched(BookingExpired::class);
    }

    public function test_booking_service_expire_unaccepted_expires_pending_booking_when_shooting_started_earlier_today(): void
    {
        Event::fake([BookingExpired::class]);

        $booking = Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->subHour(),
        ]);

        app(BookingService::class)->expireUnaccepted($booking);

        $booking->refresh();
        $this->assertSame(BookingStatus::Expired, $booking->status);
        Event::assertDispatched(BookingExpired::class);
    }
}
