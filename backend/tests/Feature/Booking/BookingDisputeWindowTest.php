<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\AdminRole;
use App\Enums\BookingStatus;
use App\Enums\DisputeResolutionOutcome;
use App\Enums\FinancialEventType;
use App\Enums\WalletCreditMotif;
use App\Events\BookingCompleted;
use App\Events\BookingDisputeSettled;
use App\Mail\BookingCompletionReminderMail;
use App\Mail\WalletCreditedFaceMail;
use App\Mail\WalletCreditedMail;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\EscrowTransaction;
use App\Models\Face;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingDisputeWindowTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private User $faceUser;

    private Face $face;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', 'UTC'));

        $producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
            'balance' => 0,
        ]);

        $this->face = Face::factory()->create(['rating_penalty' => 0.0]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makePendingSettlement(BookingStatus $status, array $attributes = []): Booking
    {
        return $this->makeBooking($status, array_merge([
            'settlement_due_at' => now()->subHour(),
        ], $attributes));
    }

    private function admin(AdminRole $role = AdminRole::Admin): Admin
    {
        return Admin::factory()->create(['role' => $role]);
    }

    // ------------------------------------------------------------------
    // reportNoShow
    // ------------------------------------------------------------------

    public function test_no_show_is_refused_before_the_day_after_the_shoot(): void
    {
        $booking = $this->makeBooking(BookingStatus::Paid, [
            'date_debut' => now()->startOfDay()->addHours(9),
        ]);

        $this->as($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/report-no-show")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_debut']);

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'paid']);
    }

    public function test_no_show_the_day_after_the_shoot_holds_the_money_in_escrow(): void
    {
        $booking = $this->makeBooking(BookingStatus::Paid, [
            'date_debut' => now()->subDay()->startOfDay(),
        ]);

        $this->as($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/report-no-show")
            ->assertOk()
            ->assertJsonPath('data.status', 'no_show');

        $booking->refresh();
        $this->assertSame(BookingStatus::NoShow, $booking->status);
        $this->assertTrue($booking->settlement_due_at->equalTo(now()->addHours(72)));
        $this->assertDatabaseHas('escrow_transactions', ['booking_id' => $booking->id, 'status' => 'locked']);
        $this->assertSame(0, $this->producerUser->fresh()->balance);
        $this->assertDatabaseMissing('wallet_transactions', ['booking_id' => $booking->id]);
        $this->assertDatabaseMissing('financial_events', [
            'booking_id' => $booking->id,
            'type' => FinancialEventType::Refund->value,
        ]);
        $this->assertEquals(0.0, $this->face->fresh()->rating_penalty);
    }

    public function test_no_show_without_locked_escrow_is_unprocessable(): void
    {
        $booking = Booking::factory()->paid()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'type_contenu' => 'Publicité',
            'date_debut' => now()->subDay(),
        ]);

        $this->as($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/report-no-show")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_no_show_is_allowed_on_confirmed_by_face_and_auto_complete_never_pays_the_face(): void
    {
        $booking = $this->makeBooking(BookingStatus::ConfirmedByFace, [
            'date_debut' => now()->subDays(5),
            'date_fin' => now()->subDays(5),
        ]);

        $this->as($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/report-no-show")
            ->assertOk()
            ->assertJsonPath('data.status', 'no_show');

        $this->artisan('bookings:auto-complete')->assertSuccessful();

        $this->assertSame(BookingStatus::NoShow, $booking->fresh()->status);
        $this->assertSame(0, $this->faceUser->fresh()->balance);
        $this->assertDatabaseHas('escrow_transactions', ['booking_id' => $booking->id, 'status' => 'locked']);
    }

    public function test_no_show_cannot_be_reported_on_confirmed_by_producer(): void
    {
        $booking = $this->makeBooking(BookingStatus::ConfirmedByProducer);

        $this->as($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/report-no-show")
            ->assertForbidden();
    }

    public function test_no_show_report_notifies_both_parties_with_the_contest_deadline(): void
    {
        $booking = $this->makeBooking(BookingStatus::Paid);

        $this->as($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/report-no-show")
            ->assertOk();

        $faceNotif = Notification::query()
            ->where('user_id', $this->faceUser->id)
            ->where('type', 'booking_no_show')
            ->firstOrFail();
        $this->assertStringContainsString('Vous pouvez contester jusqu\'au 13/10/2026 13:00', $faceNotif->data['message']);

        $producerNotif = Notification::query()
            ->where('user_id', $this->producerUser->id)
            ->where('type', 'booking_no_show')
            ->firstOrFail();
        $this->assertStringContainsString('13/10/2026 13:00', $producerNotif->data['message']);
    }

    public function test_no_wallet_credited_email_is_sent_at_report_time(): void
    {
        Mail::fake();

        $booking = $this->makeBooking(BookingStatus::Paid);

        $this->as($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/report-no-show")
            ->assertOk();

        Mail::assertNotQueued(WalletCreditedMail::class);
    }

    // ------------------------------------------------------------------
    // cancel (Producer)
    // ------------------------------------------------------------------

    public function test_late_producer_cancel_on_paid_booking_holds_the_money(): void
    {
        $booking = $this->makeBooking(BookingStatus::Paid, [
            'date_debut' => now()->startOfDay(),
            'date_fin' => now()->startOfDay(),
        ]);

        $this->as($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/cancel", [
                'cancellation_reason' => 'other',
                'custom_cancellation_reason' => 'Imprévu de production.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled_by_producer');

        $booking->refresh();
        $this->assertTrue($booking->settlement_due_at->equalTo(now()->addHours(72)));
        $this->assertDatabaseHas('escrow_transactions', ['booking_id' => $booking->id, 'status' => 'locked']);
        $this->assertSame(0, $this->producerUser->fresh()->balance);
        $this->assertDatabaseMissing('financial_events', [
            'booking_id' => $booking->id,
            'type' => FinancialEventType::Refund->value,
        ]);
    }

    public function test_late_producer_cancel_tells_the_face_she_can_contest_and_sends_no_wallet_email(): void
    {
        Mail::fake();

        $booking = $this->makeBooking(BookingStatus::Paid, [
            'date_debut' => now()->startOfDay(),
            'date_fin' => now()->startOfDay(),
        ]);

        $this->as($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/cancel", [
                'cancellation_reason' => 'other',
                'custom_cancellation_reason' => 'Imprévu de production.',
            ])
            ->assertOk();

        $notif = Notification::query()
            ->where('user_id', $this->faceUser->id)
            ->where('type', 'booking_cancelled')
            ->firstOrFail();
        $this->assertStringContainsString('contester jusqu\'au 13/10/2026 13:00', $notif->data['message']);
        Mail::assertNotQueued(WalletCreditedMail::class);
    }

    public function test_producer_cancel_before_the_shoot_day_still_refunds_90_percent_immediately(): void
    {
        $booking = $this->makeBooking(BookingStatus::Paid, [
            'date_debut' => now()->addDays(3),
            'date_fin' => now()->addDays(3),
        ]);

        $this->as($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/cancel", [
                'cancellation_reason' => 'other',
                'custom_cancellation_reason' => 'Imprévu de production.',
            ])
            ->assertOk();

        $booking->refresh();
        $this->assertNull($booking->settlement_due_at);
        $this->assertSame(99000, $this->producerUser->fresh()->balance);
        $this->assertDatabaseHas('escrow_transactions', ['booking_id' => $booking->id, 'status' => 'refunded']);
    }

    // ------------------------------------------------------------------
    // contest (Face)
    // ------------------------------------------------------------------

    public function test_face_can_contest_within_the_window(): void
    {
        $booking = $this->makeBooking(BookingStatus::NoShow, ['settlement_due_at' => now()->addHours(10)]);

        $this->as($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/contest", ['message' => 'J\'étais présente sur place.'])
            ->assertOk()
            ->assertJsonPath('data.disputed_at', now()->toIso8601String());

        $booking->refresh();
        $this->assertNotNull($booking->disputed_at);
        $this->assertSame('J\'étais présente sur place.', $booking->dispute_message);
        $this->assertTrue(Notification::query()
            ->where('user_id', $this->producerUser->id)
            ->where('type', 'booking_dispute_opened')
            ->exists());
    }

    public function test_contest_after_the_window_is_unprocessable(): void
    {
        $booking = $this->makeBooking(BookingStatus::NoShow, ['settlement_due_at' => now()->subMinute()]);

        $this->as($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/contest", ['message' => 'J\'étais présente sur place.'])
            ->assertUnprocessable();

        $this->assertNull($booking->fresh()->disputed_at);
    }

    public function test_contest_by_another_user_is_forbidden(): void
    {
        $booking = $this->makeBooking(BookingStatus::NoShow, ['settlement_due_at' => now()->addHours(10)]);

        $this->as($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/contest", ['message' => 'Je conteste ceci ici.'])
            ->assertForbidden();
    }

    public function test_contesting_twice_is_rejected(): void
    {
        $booking = $this->makeBooking(BookingStatus::NoShow, ['settlement_due_at' => now()->addHours(10)]);

        $this->as($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/contest", ['message' => 'J\'étais présente sur place.'])
            ->assertOk();

        $this->as($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/contest", ['message' => 'J\'étais présente sur place.'])
            ->assertStatus(403);
    }

    public function test_service_rejects_a_second_contest_even_with_a_stale_model(): void
    {
        $booking = $this->makeBooking(BookingStatus::NoShow, ['settlement_due_at' => now()->addHours(10)]);
        $stale = Booking::query()->findOrFail($booking->id);

        $service = app(\App\Services\BookingService::class);
        $service->contest($booking, $this->faceUser, 'J\'étais présente sur place.');

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->contest($stale, $this->faceUser, 'J\'étais présente sur place.');
    }

    public function test_contest_on_a_legacy_settled_no_show_is_forbidden(): void
    {
        $booking = $this->makeBooking(BookingStatus::NoShow, ['settlement_due_at' => null], 'refunded');

        $this->as($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/contest", ['message' => 'J\'étais présente sur place.'])
            ->assertForbidden();
    }

    public function test_contest_message_is_validated(): void
    {
        $booking = $this->makeBooking(BookingStatus::NoShow, ['settlement_due_at' => now()->addHours(10)]);

        $this->as($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/contest", ['message' => 'court'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['message']);
    }

    public function test_contest_is_possible_on_a_late_cancellation(): void
    {
        $booking = $this->makeBooking(BookingStatus::CancelledByProducer, ['settlement_due_at' => now()->addHours(10)]);

        $this->as($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/contest", ['message' => 'Je me suis rendue disponible.'])
            ->assertOk();

        $this->assertNotNull($booking->fresh()->disputed_at);
    }

    // ------------------------------------------------------------------
    // bookings:settle-disputes
    // ------------------------------------------------------------------

    public function test_settle_command_refunds_producer_100_percent_on_uncontested_no_show(): void
    {
        Event::fake([BookingDisputeSettled::class]);

        $booking = $this->makePendingSettlement(BookingStatus::NoShow);

        $this->artisan('bookings:settle-disputes')->assertSuccessful();

        $booking->refresh();
        $this->assertSame(110000, $this->producerUser->fresh()->balance);
        $this->assertDatabaseHas('escrow_transactions', ['booking_id' => $booking->id, 'status' => 'refunded']);
        $this->assertEquals(1.0, $this->face->fresh()->rating_penalty);
        $this->assertNotNull($booking->dispute_resolved_at);
        $this->assertSame('favor_producer', $booking->dispute_outcome);
        $this->assertNull($booking->dispute_resolved_by);
        $this->assertDatabaseHas('financial_events', [
            'booking_id' => $booking->id,
            'type' => FinancialEventType::Refund->value,
            'amount' => 110000,
        ]);
        Event::assertDispatchedTimes(BookingDisputeSettled::class, 1);

        // Second run: nothing more happens.
        $this->artisan('bookings:settle-disputes')->assertSuccessful();

        $this->assertSame(110000, $this->producerUser->fresh()->balance);
        $this->assertEquals(1.0, $this->face->fresh()->rating_penalty);
        Event::assertDispatchedTimes(BookingDisputeSettled::class, 1);
    }

    public function test_settle_command_refunds_producer_90_percent_on_uncontested_late_cancellation(): void
    {
        $booking = $this->makePendingSettlement(BookingStatus::CancelledByProducer, [
            'cancellation_reason' => 'other',
        ]);

        $this->artisan('bookings:settle-disputes')->assertSuccessful();

        $this->assertSame(99000, $this->producerUser->fresh()->balance);
        $this->assertDatabaseHas('escrow_transactions', ['booking_id' => $booking->id, 'status' => 'refunded']);
        $this->assertEquals(0.0, $this->face->fresh()->rating_penalty);
        $this->assertSame(BookingStatus::CancelledByProducer, $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->dispute_resolved_at);
    }

    public function test_settle_command_leaves_contested_and_not_yet_due_bookings_untouched(): void
    {
        $contested = $this->makePendingSettlement(BookingStatus::NoShow, ['disputed_at' => now()->subMinutes(5)]);
        $notDue = $this->makeBooking(BookingStatus::NoShow, ['settlement_due_at' => now()->addHour()]);

        $this->artisan('bookings:settle-disputes')->assertSuccessful();

        $this->assertSame(0, $this->producerUser->fresh()->balance);
        $this->assertNull($contested->fresh()->dispute_resolved_at);
        $this->assertNull($notDue->fresh()->dispute_resolved_at);
        $this->assertDatabaseHas('escrow_transactions', ['booking_id' => $contested->id, 'status' => 'locked']);
    }

    public function test_settlement_sends_wallet_credited_email_and_notifies_both_parties(): void
    {
        Mail::fake();

        $this->makePendingSettlement(BookingStatus::NoShow);

        $this->artisan('bookings:settle-disputes')->assertSuccessful();

        Mail::assertQueued(
            WalletCreditedMail::class,
            fn (WalletCreditedMail $mail): bool => $mail->hasTo($this->producerUser->email)
                && $mail->amount === 110000
                && $mail->motif === WalletCreditMotif::BookingNoShowRefund,
        );
        $this->assertTrue(Notification::query()
            ->where('user_id', $this->producerUser->id)
            ->where('type', 'booking_dispute_settled')
            ->exists());
        $this->assertTrue(Notification::query()
            ->where('user_id', $this->faceUser->id)
            ->where('type', 'booking_dispute_settled')
            ->exists());
    }

    public function test_settlement_of_late_cancellation_uses_the_cancellation_motif(): void
    {
        Mail::fake();

        $this->makePendingSettlement(BookingStatus::CancelledByProducer, ['cancellation_reason' => 'other']);

        $this->artisan('bookings:settle-disputes')->assertSuccessful();

        Mail::assertQueued(
            WalletCreditedMail::class,
            fn (WalletCreditedMail $mail): bool => $mail->amount === 99000
                && $mail->motif === WalletCreditMotif::BookingCancellationRefund,
        );
    }

    // ------------------------------------------------------------------
    // Admin resolution
    // ------------------------------------------------------------------

    public function test_admin_favor_face_on_no_show_completes_the_booking_and_pays_the_face(): void
    {
        Event::fake([BookingCompleted::class]);

        $booking = $this->makeBooking(BookingStatus::NoShow, [
            'settlement_due_at' => now()->addHours(10),
            'disputed_at' => now()->subHour(),
            'dispute_message' => 'J\'étais présente.',
        ]);
        $admin = $this->admin();

        $this->as($admin)
            ->postJson("/api/v1/admin/booking-disputes/{$booking->uuid}/resolve", [
                'outcome' => DisputeResolutionOutcome::FavorFace->value,
                'notes' => 'Preuves de présence fournies.',
            ])
            ->assertOk();

        $booking->refresh();
        $this->assertSame(BookingStatus::Completed, $booking->status);
        $this->assertSame(90000, $this->faceUser->fresh()->balance);
        $this->assertSame(0, $this->producerUser->fresh()->balance);
        $this->assertDatabaseHas('escrow_transactions', ['booking_id' => $booking->id, 'status' => 'released']);
        $this->assertSame('favor_face', $booking->dispute_outcome);
        $this->assertSame($admin->id, $booking->dispute_resolved_by);
        $this->assertSame('Preuves de présence fournies.', $booking->dispute_admin_notes);
        $this->assertEquals(0.0, $this->face->fresh()->rating_penalty);
        Event::assertDispatched(BookingCompleted::class);
    }

    public function test_admin_favor_face_on_late_cancellation_keeps_status_and_pays_the_face(): void
    {
        Mail::fake();

        $booking = $this->makeBooking(BookingStatus::CancelledByProducer, [
            'settlement_due_at' => now()->addHours(10),
            'disputed_at' => now()->subHour(),
            'dispute_message' => 'Je m\'étais rendue disponible.',
            'cancellation_reason' => 'other',
        ]);

        $this->as($this->admin())
            ->postJson("/api/v1/admin/booking-disputes/{$booking->uuid}/resolve", [
                'outcome' => 'favor_face',
                'notes' => 'Annulation trop tardive.',
            ])
            ->assertOk();

        $booking->refresh();
        $this->assertSame(BookingStatus::CancelledByProducer, $booking->status);
        $this->assertSame(90000, $this->faceUser->fresh()->balance);
        $this->assertDatabaseHas('escrow_transactions', ['booking_id' => $booking->id, 'status' => 'released']);
        Mail::assertQueued(
            WalletCreditedFaceMail::class,
            fn (WalletCreditedFaceMail $mail): bool => $mail->amount === 90000,
        );
    }

    public function test_admin_favor_producer_refunds_as_automatic_settlement_with_admin_trace(): void
    {
        $noShow = $this->makeBooking(BookingStatus::NoShow, [
            'settlement_due_at' => now()->addHours(10),
            'disputed_at' => now()->subHour(),
        ]);
        $cancelled = $this->makeBooking(BookingStatus::CancelledByProducer, [
            'settlement_due_at' => now()->addHours(10),
            'disputed_at' => now()->subHour(),
            'cancellation_reason' => 'other',
        ]);
        $admin = $this->admin();

        $this->as($admin)
            ->postJson("/api/v1/admin/booking-disputes/{$noShow->uuid}/resolve", [
                'outcome' => 'favor_producer',
                'notes' => 'Absence avérée.',
            ])
            ->assertOk();
        $this->as($admin)
            ->postJson("/api/v1/admin/booking-disputes/{$cancelled->uuid}/resolve", [
                'outcome' => 'favor_producer',
                'notes' => 'Annulation légitime.',
            ])
            ->assertOk();

        $this->assertSame(110000 + 99000, $this->producerUser->fresh()->balance);
        $this->assertEquals(1.0, $this->face->fresh()->rating_penalty);
        $this->assertSame($admin->id, $noShow->fresh()->dispute_resolved_by);
        $this->assertSame(BookingStatus::NoShow, $noShow->fresh()->status);
    }

    public function test_admin_resolve_on_a_non_disputed_booking_is_unprocessable(): void
    {
        $booking = $this->makePendingSettlement(BookingStatus::NoShow);

        $this->as($this->admin())
            ->postJson("/api/v1/admin/booking-disputes/{$booking->uuid}/resolve", [
                'outcome' => 'favor_face',
                'notes' => 'Test de garde.',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseHas('escrow_transactions', ['booking_id' => $booking->id, 'status' => 'locked']);
    }

    public function test_admin_cannot_resolve_twice(): void
    {
        $booking = $this->makeBooking(BookingStatus::NoShow, [
            'settlement_due_at' => now()->addHours(10),
            'disputed_at' => now()->subHour(),
        ]);
        $admin = $this->admin();

        $payload = ['outcome' => 'favor_producer', 'notes' => 'Absence avérée.'];
        $this->as($admin)->postJson("/api/v1/admin/booking-disputes/{$booking->uuid}/resolve", $payload)->assertOk();
        $this->as($admin)->postJson("/api/v1/admin/booking-disputes/{$booking->uuid}/resolve", $payload)->assertUnprocessable();

        $this->assertSame(110000, $this->producerUser->fresh()->balance);
    }

    public function test_resolve_requires_notes_and_valid_outcome(): void
    {
        $booking = $this->makeBooking(BookingStatus::NoShow, [
            'settlement_due_at' => now()->addHours(10),
            'disputed_at' => now()->subHour(),
        ]);

        $this->as($this->admin())
            ->postJson("/api/v1/admin/booking-disputes/{$booking->uuid}/resolve", ['outcome' => 'nope', 'notes' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['outcome', 'notes']);
    }

    public function test_resolve_is_forbidden_for_non_admin_and_editor_and_unauthenticated(): void
    {
        $booking = $this->makeBooking(BookingStatus::NoShow, [
            'settlement_due_at' => now()->addHours(10),
            'disputed_at' => now()->subHour(),
        ]);
        $payload = ['outcome' => 'favor_face', 'notes' => 'Test de garde.'];
        $url = "/api/v1/admin/booking-disputes/{$booking->uuid}/resolve";

        $this->postJson($url, $payload)->assertUnauthorized();
        $this->as($this->faceUser)->postJson($url, $payload)->assertStatus(403);
        $this->as($this->admin(AdminRole::Editor))->postJson($url, $payload)->assertStatus(403);
    }

    public function test_admin_index_lists_disputes_and_stale_paid_bookings(): void
    {
        $disputed = $this->makeBooking(BookingStatus::NoShow, [
            'settlement_due_at' => now()->addHours(10),
            'disputed_at' => now()->subHour(),
            'dispute_message' => 'J\'étais présente.',
        ]);
        $notDisputed = $this->makePendingSettlement(BookingStatus::NoShow);
        $legacy = $this->makeBooking(BookingStatus::Paid, [
            'date_debut' => now()->subDays(60),
            'date_fin' => now()->subDays(60),
        ]);
        $recent = $this->makeBooking(BookingStatus::Paid, [
            'date_debut' => now()->subDays(3),
            'date_fin' => now()->subDays(3),
        ]);

        $response = $this->as($this->admin())->getJson('/api/v1/admin/booking-disputes');

        $response->assertOk();
        $disputeIds = collect($response->json('data.disputes'))->pluck('id')->all();
        $this->assertSame([$disputed->uuid], $disputeIds);
        $this->assertSame('J\'étais présente.', $response->json('data.disputes.0.dispute_message'));

        $staleIds = collect($response->json('data.stale_paid'))->pluck('id')->all();
        $this->assertContains($legacy->uuid, $staleIds);
        $this->assertNotContains($recent->uuid, $staleIds);
        $this->assertNotContains($notDisputed->uuid, $staleIds);
        $this->assertSame(60, collect($response->json('data.stale_paid'))->firstWhere('id', $legacy->uuid)['days_since_date_fin']);
    }

    public function test_admin_index_is_forbidden_for_a_regular_user(): void
    {
        $this->as($this->faceUser)->getJson('/api/v1/admin/booking-disputes')->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // Silent paid bookings
    // ------------------------------------------------------------------

    public function test_reminder_is_sent_once_two_days_after_the_shoot_day(): void
    {
        Mail::fake();

        $due = $this->makeBooking(BookingStatus::Paid, [
            'date_debut' => Carbon::parse('2026-10-08 00:00:00'),
            'date_fin' => Carbon::parse('2026-10-08 18:00:00'),
        ]);
        $tooEarly = $this->makeBooking(BookingStatus::Paid, [
            'date_debut' => Carbon::parse('2026-10-09 00:00:00'),
            'date_fin' => Carbon::parse('2026-10-09 18:00:00'),
        ]);

        $this->artisan('bookings:remind-pending-confirmation')->assertSuccessful();

        $this->assertNotNull($due->fresh()->completion_reminder_sent_at);
        $this->assertNull($tooEarly->fresh()->completion_reminder_sent_at);
        $this->assertTrue(Notification::query()->where('user_id', $this->faceUser->id)->where('type', 'booking_completion_reminder')->exists());
        $this->assertTrue(Notification::query()->where('user_id', $this->producerUser->id)->where('type', 'booking_completion_reminder')->exists());
        Mail::assertQueued(BookingCompletionReminderMail::class, 1);

        $this->artisan('bookings:remind-pending-confirmation')->assertSuccessful();

        $this->assertSame(2, Notification::query()->where('type', 'booking_completion_reminder')->count());
        Mail::assertQueued(BookingCompletionReminderMail::class, 1);
    }

    public function test_legacy_paid_booking_is_neither_reminded_nor_auto_completed(): void
    {
        Mail::fake();

        $legacy = $this->makeBooking(BookingStatus::Paid, [
            'date_debut' => now()->subDays(60),
            'date_fin' => now()->subDays(60),
        ]);

        $this->artisan('bookings:remind-pending-confirmation')->assertSuccessful();
        $this->assertNull($legacy->fresh()->completion_reminder_sent_at);

        // Même avec un rappel déjà posé, un legacy n'est jamais payé automatiquement.
        $legacy->update(['completion_reminder_sent_at' => now()->subDays(50)]);
        $this->artisan('bookings:auto-complete')->assertSuccessful();

        $this->assertSame(BookingStatus::Paid, $legacy->fresh()->status);
        $this->assertSame(0, $this->faceUser->fresh()->balance);
        Mail::assertNothingQueued();
    }

    public function test_auto_complete_pays_the_face_eight_days_after_the_shoot_day_only_after_a_reminder(): void
    {
        Event::fake([BookingCompleted::class]);

        $reminded = $this->makeBooking(BookingStatus::Paid, [
            'date_debut' => Carbon::parse('2026-10-02 00:00:00'),
            'date_fin' => Carbon::parse('2026-10-02 18:00:00'),
            'completion_reminder_sent_at' => Carbon::parse('2026-10-04 08:00:00'),
        ]);
        $notReminded = $this->makeBooking(BookingStatus::Paid, [
            'date_debut' => Carbon::parse('2026-10-02 00:00:00'),
            'date_fin' => Carbon::parse('2026-10-02 18:00:00'),
        ]);
        $tooEarly = $this->makeBooking(BookingStatus::Paid, [
            'date_debut' => Carbon::parse('2026-10-03 00:00:00'),
            'date_fin' => Carbon::parse('2026-10-03 18:00:00'),
            'completion_reminder_sent_at' => Carbon::parse('2026-10-05 08:00:00'),
        ]);

        $this->artisan('bookings:auto-complete')->assertSuccessful();

        $this->assertSame(BookingStatus::Completed, $reminded->fresh()->status);
        $this->assertSame(90000, $this->faceUser->fresh()->balance);
        $this->assertSame(BookingStatus::Paid, $notReminded->fresh()->status);
        $this->assertSame(BookingStatus::Paid, $tooEarly->fresh()->status);
        Event::assertDispatchedTimes(BookingCompleted::class, 1);
    }

    public function test_auto_complete_never_touches_no_show_or_cancelled_bookings(): void
    {
        $noShow = $this->makePendingSettlement(BookingStatus::NoShow, [
            'date_fin' => now()->subDays(9),
            'completion_reminder_sent_at' => now()->subDays(7),
        ]);

        $this->artisan('bookings:auto-complete')->assertSuccessful();

        $this->assertSame(BookingStatus::NoShow, $noShow->fresh()->status);
        $this->assertSame(0, $this->faceUser->fresh()->balance);
    }
}
