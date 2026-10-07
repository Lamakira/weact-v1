<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\AdminRole;
use App\Enums\BookingStatus;
use App\Enums\DisputeResolutionOutcome;
use App\Mail\BookingCancelledMail;
use App\Mail\BookingFaceConfirmedMail;
use App\Mail\BookingNoShowFaceMail;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\EscrowTransaction;
use App\Models\Face;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Vague 3 de revue du lot A2 : règle « legacy », échéances cohérentes, emails, cohérence du scope.
 */
class BookingDisputeWindowLegacyTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private User $faceUser;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.business_timezone' => 'Africa/Porto-Novo']);
        Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', 'UTC'));

        $producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
            'balance' => 0,
        ]);

        $face = Face::factory()->create(['rating_penalty' => 0.0]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'balance' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function as(User|Admin $user): static
    {
        return $this->withToken($user->createToken('test-token')->plainTextToken);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeBooking(BookingStatus $status, array $attributes = [], string $escrow = 'locked'): Booking
    {
        $booking = Booking::factory()->create(array_merge([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => $status,
            'type_contenu' => 'Publicité',
            'date_debut' => now()->subDay(),
            'date_fin' => now()->subDay(),
            'tarif_base' => 100000,
            'montant_total_producteur' => 110000,
            'montant_face_recoit' => 90000,
        ], $attributes));

        EscrowTransaction::factory()->create([
            'booking_id' => $booking->id,
            'amount' => 90000,
            'status' => $escrow,
        ]);

        return $booking;
    }

    // ------------------------------------------------------------------
    // H1 : booking legacy confirmé par la Face seule
    // ------------------------------------------------------------------

    public function test_legacy_paid_booking_confirmed_by_the_face_alone_is_never_auto_paid(): void
    {
        $booking = $this->makeBooking(BookingStatus::Paid, [
            'date_debut' => now()->subDays(60),
            'date_fin' => now()->subDays(60),
        ]);

        $this->as($this->faceUser)->postJson("/api/v1/bookings/{$booking->uuid}/confirm")->assertOk();

        Carbon::setTestNow(now()->addDays(10));
        $this->artisan('bookings:auto-complete')->assertSuccessful();

        $this->assertSame(BookingStatus::ConfirmedByFace, $booking->fresh()->status);
        $this->assertSame(0, $this->faceUser->fresh()->balance);

        // Le Producteur peut toujours confirmer explicitement.
        $this->as($this->producerUser)->postJson("/api/v1/bookings/{$booking->uuid}/confirm")->assertOk();

        $this->assertSame(BookingStatus::Completed, $booking->fresh()->status);
        $this->assertSame(90000, $this->faceUser->fresh()->balance);
    }

    public function test_legacy_booking_confirmed_by_the_face_stays_reportable_as_no_show(): void
    {
        $booking = $this->makeBooking(BookingStatus::ConfirmedByFace, [
            'date_debut' => now()->subDays(60),
            'date_fin' => now()->subDays(60),
            'face_confirmed_at' => now(),
        ]);

        $this->as($this->producerUser)->postJson("/api/v1/bookings/{$booking->uuid}/report-no-show")
            ->assertOk()
            ->assertJsonPath('data.status', 'no_show');
    }

    public function test_legacy_confirmed_by_face_without_face_confirmed_at_keeps_the_72h_rule(): void
    {
        $booking = $this->makeBooking(BookingStatus::ConfirmedByFace, [
            'date_debut' => now()->subDays(60),
            'date_fin' => now()->subDays(60),
        ]);

        $this->artisan('bookings:auto-complete')->assertSuccessful();

        $this->assertSame(BookingStatus::Completed, $booking->fresh()->status);
    }

    public function test_stale_paid_lists_legacy_bookings_confirmed_by_the_face_alone(): void
    {
        $legacyConfirmed = $this->makeBooking(BookingStatus::ConfirmedByFace, [
            'date_debut' => now()->subDays(60),
            'date_fin' => now()->subDays(60),
            'face_confirmed_at' => now()->subHour(),
        ]);
        $recentConfirmed = $this->makeBooking(BookingStatus::ConfirmedByFace, [
            'date_debut' => now()->subDays(10),
            'date_fin' => now()->subDays(10),
            'face_confirmed_at' => now()->subHour(),
        ]);
        $admin = Admin::factory()->create(['role' => AdminRole::Admin]);

        $rows = collect($this->as($admin)->getJson('/api/v1/admin/booking-disputes')->assertOk()->json('data.stale_paid'))->keyBy('id');

        $this->assertArrayHasKey($legacyConfirmed->uuid, $rows->all());
        $this->assertSame('confirmed_by_face', $rows[$legacyConfirmed->uuid]['status']);
        $this->assertTrue($rows[$legacyConfirmed->uuid]['is_legacy']);
        $this->assertNull($rows[$legacyConfirmed->uuid]['auto_complete_due_at']);
        $this->assertArrayNotHasKey($recentConfirmed->uuid, $rows->all());
    }

    public function test_legacy_helper_ignores_the_age_once_the_reminder_was_sent(): void
    {
        $booking = new Booking([
            'date_fin' => now()->subDays(60),
            'completion_reminder_sent_at' => now()->subDay(),
        ]);
        $this->assertFalse($booking->isLegacyForAutoPayment());

        $booking->completion_reminder_sent_at = null;
        $this->assertTrue($booking->isLegacyForAutoPayment());
        $this->assertFalse($booking->isLegacyForAutoPayment(now()->subDays(40)));
    }

    // ------------------------------------------------------------------
    // M1 : rappel tardif (D+25) tenu jusqu'à son échéance
    // ------------------------------------------------------------------

    public function test_reminder_sent_at_day_25_is_honoured_at_day_31_on_the_silent_path(): void
    {
        Mail::fake();

        $booking = $this->makeBooking(BookingStatus::Paid, [
            'date_debut' => now()->subDays(25),
            'date_fin' => now()->subDays(25),
        ]);

        $this->artisan('bookings:remind-pending-confirmation')->assertSuccessful();
        $this->assertNotNull($booking->fresh()->completion_reminder_sent_at);

        Carbon::setTestNow(now()->addDays(6)->subMinute());
        $this->artisan('bookings:auto-complete')->assertSuccessful();
        $this->assertSame(BookingStatus::Paid, $booking->fresh()->status);

        Carbon::setTestNow(now()->addMinute());
        $this->artisan('bookings:auto-complete')->assertSuccessful();
        $this->assertSame(BookingStatus::Completed, $booking->fresh()->status);
        $this->assertSame(90000, $this->faceUser->fresh()->balance);
    }

    public function test_reminder_sent_at_day_25_is_honoured_at_day_31_on_the_confirmed_by_face_path(): void
    {
        Mail::fake();

        $booking = $this->makeBooking(BookingStatus::Paid, [
            'date_debut' => now()->subDays(25),
            'date_fin' => now()->subDays(25),
        ]);

        $this->artisan('bookings:remind-pending-confirmation')->assertSuccessful();
        $this->as($this->faceUser)->postJson("/api/v1/bookings/{$booking->uuid}/confirm")->assertOk();

        Carbon::setTestNow(now()->addDays(6)->subMinute());
        $this->artisan('bookings:auto-complete')->assertSuccessful();
        $this->assertSame(BookingStatus::ConfirmedByFace, $booking->fresh()->status);

        Carbon::setTestNow(now()->addMinute());
        $this->artisan('bookings:auto-complete')->assertSuccessful();
        $this->assertSame(BookingStatus::Completed, $booking->fresh()->status);
    }

    // ------------------------------------------------------------------
    // M2 / L1 : emails
    // ------------------------------------------------------------------

    public function test_producer_is_emailed_the_deadline_when_the_face_confirms_first(): void
    {
        Mail::fake();

        $booking = $this->makeBooking(BookingStatus::Paid, [
            'date_debut' => now()->subDays(10),
            'date_fin' => now()->subDays(10),
        ]);

        $this->as($this->faceUser)->postJson("/api/v1/bookings/{$booking->uuid}/confirm")->assertOk();

        Mail::assertQueued(
            BookingFaceConfirmedMail::class,
            fn (BookingFaceConfirmedMail $mail): bool => $mail->hasTo($this->producerUser->email)
                && $mail->dueAt === '13/10/2026 13:00',
        );
    }

    public function test_face_is_emailed_at_no_show_report_with_the_contest_deadline(): void
    {
        Mail::fake();

        $booking = $this->makeBooking(BookingStatus::Paid);

        $this->as($this->producerUser)->postJson("/api/v1/bookings/{$booking->uuid}/report-no-show")->assertOk();

        Mail::assertQueued(
            BookingNoShowFaceMail::class,
            function (BookingNoShowFaceMail $mail): bool {
                $mail->assertSeeInHtml('13/10/2026 13:00');
                $mail->assertSeeInHtml('/face/bookings/');

                return $mail->hasTo($this->faceUser->email);
            },
        );
    }

    public function test_late_cancellation_email_mentions_the_contest_deadline(): void
    {
        Mail::fake();

        $booking = $this->makeBooking(BookingStatus::Paid, [
            'date_debut' => now()->startOfDay(),
            'date_fin' => now()->startOfDay(),
        ]);

        $this->as($this->producerUser)->postJson("/api/v1/bookings/{$booking->uuid}/cancel", [
            'cancellation_reason' => 'other',
            'custom_cancellation_reason' => 'Imprévu de production.',
        ])->assertOk();

        Mail::assertQueued(
            BookingCancelledMail::class,
            function (BookingCancelledMail $mail): bool {
                $mail->assertSeeInHtml('contester jusqu\'au 13/10/2026 13:00', false);

                return $mail->hasTo($this->faceUser->email);
            },
        );
    }

    public function test_early_cancellation_email_has_no_contest_mention(): void
    {
        Mail::fake();

        $booking = $this->makeBooking(BookingStatus::Paid, [
            'date_debut' => now()->addDays(3),
            'date_fin' => now()->addDays(3),
        ]);

        $this->as($this->producerUser)->postJson("/api/v1/bookings/{$booking->uuid}/cancel", [
            'cancellation_reason' => 'other',
            'custom_cancellation_reason' => 'Imprévu de production.',
        ])->assertOk();

        Mail::assertQueued(
            BookingCancelledMail::class,
            function (BookingCancelledMail $mail): bool {
                $mail->assertDontSeeInHtml('contester');

                return true;
            },
        );
    }

    // ------------------------------------------------------------------
    // L2 : le scope tient compte de l'escrow
    // ------------------------------------------------------------------

    public function test_pending_settlement_requires_a_locked_escrow(): void
    {
        $locked = $this->makeBooking(BookingStatus::NoShow, ['settlement_due_at' => now()->addHours(5)]);
        $refunded = $this->makeBooking(BookingStatus::NoShow, ['settlement_due_at' => now()->addHours(5)], 'refunded');

        $ids = Booking::query()->pendingSettlement()->pluck('id')->all();

        $this->assertContains($locked->id, $ids);
        $this->assertNotContains($refunded->id, $ids);

        $cancelled = collect($this->as($this->faceUser)->getJson('/api/v1/bookings?status=cancelled')->json('data'))->pluck('id')->all();
        $this->assertContains($refunded->uuid, $cancelled);
        $this->assertNotContains($locked->uuid, $cancelled);
    }

    // ------------------------------------------------------------------
    // L3 : relance non perdue si le Producteur n'a pu être prévenu
    // ------------------------------------------------------------------

    public function test_reminder_claim_is_released_when_the_producer_cannot_be_notified(): void
    {
        $booking = $this->makeBooking(BookingStatus::Paid, [
            'date_debut' => now()->subDays(5),
            'date_fin' => now()->subDays(5),
        ]);

        $producerId = $this->producerUser->id;
        Notification::creating(function (Notification $notification) use ($producerId): void {
            if ($notification->user_id === $producerId) {
                throw new \RuntimeException('notification down');
            }
        });
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('mail down'));

        $this->artisan('bookings:remind-pending-confirmation')->assertSuccessful();

        $this->assertNull($booking->fresh()->completion_reminder_sent_at);
        $this->assertFalse(Notification::query()->where('type', 'booking_completion_reminder')->exists());
    }

    public function test_reminder_is_kept_when_only_one_producer_channel_fails(): void
    {
        $booking = $this->makeBooking(BookingStatus::Paid, [
            'date_debut' => now()->subDays(5),
            'date_fin' => now()->subDays(5),
        ]);

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('mail down'));

        $this->artisan('bookings:remind-pending-confirmation')->assertSuccessful();

        $this->assertNotNull($booking->fresh()->completion_reminder_sent_at);
        $this->assertTrue(Notification::query()->where('user_id', $this->producerUser->id)->where('type', 'booking_completion_reminder')->exists());
    }

    // ------------------------------------------------------------------
    // L5 : idempotence du règlement sous verrou
    // ------------------------------------------------------------------

    public function test_settling_twice_through_the_service_never_double_credits(): void
    {
        $booking = $this->makeBooking(BookingStatus::NoShow, ['settlement_due_at' => now()->subHour()]);
        $service = app(BookingService::class);

        $first = $service->settleDispute($booking, DisputeResolutionOutcome::FavorProducer);
        $second = $service->settleDispute($booking, DisputeResolutionOutcome::FavorProducer);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertSame(110000, $this->producerUser->fresh()->balance);
        $this->assertEquals(1.0, Face::query()->first()->rating_penalty);
    }
}
