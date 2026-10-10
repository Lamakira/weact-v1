<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\AdminRole;
use App\Enums\BookingStatus;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\EscrowTransaction;
use App\Models\Face;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Vague 2 de revue du lot A2 : deadline de paiement auto, filtres, wallet, fuseau, copies.
 */
class BookingDisputeWindowReviewTest extends TestCase
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
        // Jeton admin émis après le second facteur (middleware admin.2fa), comme Tests\TestCase::actingAs.
        $abilities = $user instanceof Admin ? [Admin::ABILITY_TWO_FACTOR] : ['*'];

        return $this->withToken($user->createToken('test-token', $abilities)->plainTextToken);
    }

    private function local(Carbon $date): string
    {
        return $date->copy()->setTimezone('Africa/Porto-Novo')->format('d/m/Y H:i');
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
    // H1 / M1 : échéance de paiement automatique
    // ------------------------------------------------------------------

    public function test_face_confirming_after_the_reminder_is_not_paid_before_the_silent_deadline(): void
    {
        $booking = $this->makeBooking(BookingStatus::Paid, [
            'date_debut' => Carbon::parse('2026-10-02 00:00:00'),
            'date_fin' => Carbon::parse('2026-10-02 18:00:00'),
            'completion_reminder_sent_at' => Carbon::parse('2026-10-04 08:00:00'),
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-05 10:00:00', 'UTC'));
        $this->as($this->faceUser)->postJson("/api/v1/bookings/{$booking->uuid}/confirm")->assertOk();
        $this->assertNotNull($booking->fresh()->face_confirmed_at);

        // date_fin + 72 h (05/10 18:00) est passé, mais pas rappel + 6 j (10/10 08:00).
        Carbon::setTestNow(Carbon::parse('2026-10-09 12:00:00', 'UTC'));
        $this->artisan('bookings:auto-complete')->assertSuccessful();
        $this->assertSame(BookingStatus::ConfirmedByFace, $booking->fresh()->status);

        Carbon::setTestNow(Carbon::parse('2026-10-10 09:00:00', 'UTC'));
        $this->artisan('bookings:auto-complete')->assertSuccessful();
        $this->assertSame(BookingStatus::Completed, $booking->fresh()->status);
        $this->assertSame(90000, $this->faceUser->fresh()->balance);
    }

    public function test_face_confirming_long_after_date_fin_still_leaves_the_producer_72_hours(): void
    {
        $booking = $this->makeBooking(BookingStatus::Paid, [
            'date_debut' => now()->subDays(10),
            'date_fin' => now()->subDays(10),
        ]);

        $this->as($this->faceUser)->postJson("/api/v1/bookings/{$booking->uuid}/confirm")->assertOk();

        $this->artisan('bookings:auto-complete')->assertSuccessful();
        $this->assertSame(BookingStatus::ConfirmedByFace, $booking->fresh()->status);

        Carbon::setTestNow(now()->addHours(71));
        $this->artisan('bookings:auto-complete')->assertSuccessful();
        $this->assertSame(BookingStatus::ConfirmedByFace, $booking->fresh()->status);

        Carbon::setTestNow(now()->addHours(2));
        $this->artisan('bookings:auto-complete')->assertSuccessful();
        $this->assertSame(BookingStatus::Completed, $booking->fresh()->status);
    }

    public function test_legacy_confirmed_by_face_without_face_confirmed_at_keeps_the_72h_after_date_fin_rule(): void
    {
        $booking = $this->makeBooking(BookingStatus::ConfirmedByFace, [
            'date_fin' => now()->subHours(80),
        ]);

        $this->artisan('bookings:auto-complete')->assertSuccessful();

        $this->assertSame(BookingStatus::Completed, $booking->fresh()->status);
    }

    public function test_producer_is_told_the_deadline_when_the_face_confirms_first(): void
    {
        $booking = $this->makeBooking(BookingStatus::Paid, [
            'date_debut' => now()->subDays(10),
            'date_fin' => now()->subDays(10),
        ]);

        $this->as($this->faceUser)->postJson("/api/v1/bookings/{$booking->uuid}/confirm")->assertOk();

        $message = Notification::query()
            ->where('user_id', $this->producerUser->id)
            ->where('type', 'booking_confirmation_pending')
            ->firstOrFail()->data['message'];

        $this->assertStringContainsString('La Face a confirmé la prestation. Si elle n\'est pas venue, signalez son absence avant le '.$this->local(now()->addHours(72)), $message);
        $this->assertStringContainsString('sinon elle sera payée automatiquement', $message);
    }

    public function test_backlog_booking_is_reminded_now_with_a_future_deadline_and_not_paid_before_it(): void
    {
        Mail::fake();

        $booking = $this->makeBooking(BookingStatus::Paid, [
            'date_debut' => now()->subDays(20),
            'date_fin' => now()->subDays(20),
        ]);

        $this->artisan('bookings:remind-pending-confirmation')->assertSuccessful();

        $message = Notification::query()
            ->where('user_id', $this->producerUser->id)
            ->where('type', 'booking_completion_reminder')
            ->firstOrFail()->data['message'];
        $this->assertStringContainsString(now()->addDays(6)->copy()->setTimezone('Africa/Porto-Novo')->format('d/m/Y'), $message);

        $this->artisan('bookings:auto-complete')->assertSuccessful();
        $this->assertSame(BookingStatus::Paid, $booking->fresh()->status);

        // Minimum de 6 jours entre la relance et le paiement : pas à rappel + 5 j 23 h.
        Carbon::setTestNow(now()->addDays(5)->addHours(23));
        $this->artisan('bookings:auto-complete')->assertSuccessful();
        $this->assertSame(BookingStatus::Paid, $booking->fresh()->status);

        Carbon::setTestNow(now()->addHour());
        $this->artisan('bookings:auto-complete')->assertSuccessful();
        $this->assertSame(BookingStatus::Completed, $booking->fresh()->status);
    }

    // ------------------------------------------------------------------
    // H2 : commande de rattrapage
    // ------------------------------------------------------------------

    public function test_backfill_skips_late_cancellations_pending_settlement(): void
    {
        $booking = $this->makeBooking(BookingStatus::CancelledByProducer, [
            'settlement_due_at' => now()->addHours(10),
            'fedapay_transaction_id' => 123456,
            'payment_mode' => 'mtn',
        ]);

        $this->artisan('bookings:backfill-cancelled-wallet-refunds')->assertSuccessful();

        $this->assertSame(0, $this->producerUser->fresh()->balance);
        $this->assertDatabaseHas('escrow_transactions', ['booking_id' => $booking->id, 'status' => 'locked']);
    }

    public function test_backfill_never_touches_a_released_escrow(): void
    {
        $booking = $this->makeBooking(BookingStatus::CancelledByProducer, [
            'settlement_due_at' => null,
            'fedapay_transaction_id' => 123457,
            'payment_mode' => 'mtn',
        ], 'released');

        $this->artisan('bookings:backfill-cancelled-wallet-refunds')->assertSuccessful();

        $this->assertSame(0, $this->producerUser->fresh()->balance);
        $this->assertDatabaseHas('escrow_transactions', ['booking_id' => $booking->id, 'status' => 'released']);
    }

    // ------------------------------------------------------------------
    // M2 : filtres de liste
    // ------------------------------------------------------------------

    public function test_bookings_pending_settlement_are_listed_as_active_not_cancelled(): void
    {
        $pending = $this->makeBooking(BookingStatus::NoShow, ['settlement_due_at' => now()->addHours(10)]);
        $pendingCancel = $this->makeBooking(BookingStatus::CancelledByProducer, ['settlement_due_at' => now()->addHours(10)]);
        $resolved = $this->makeBooking(BookingStatus::NoShow, [
            'settlement_due_at' => now()->subDay(),
            'dispute_resolved_at' => now()->subHour(),
        ]);
        $legacy = $this->makeBooking(BookingStatus::NoShow, ['settlement_due_at' => null], 'refunded');

        $active = collect($this->as($this->faceUser)->getJson('/api/v1/bookings?status=active')->assertOk()->json('data'))->pluck('id')->all();
        $cancelled = collect($this->as($this->faceUser)->getJson('/api/v1/bookings?status=cancelled')->assertOk()->json('data'))->pluck('id')->all();

        $this->assertContains($pending->uuid, $active);
        $this->assertContains($pendingCancel->uuid, $active);
        $this->assertNotContains($resolved->uuid, $active);
        $this->assertNotContains($legacy->uuid, $active);

        $this->assertNotContains($pending->uuid, $cancelled);
        $this->assertNotContains($pendingCancel->uuid, $cancelled);
        $this->assertContains($resolved->uuid, $cancelled);
        $this->assertContains($legacy->uuid, $cancelled);
    }

    // ------------------------------------------------------------------
    // M3 : wallet
    // ------------------------------------------------------------------

    public function test_wallet_separates_held_disputed_funds_from_pending_escrow(): void
    {
        $this->makeBooking(BookingStatus::Paid);
        $this->makeBooking(BookingStatus::NoShow, ['settlement_due_at' => now()->addHours(10)]);

        $this->as($this->faceUser)->getJson('/api/v1/wallet')
            ->assertOk()
            ->assertJsonPath('data.pending_escrow', 90000)
            ->assertJsonPath('data.held_in_dispute', 90000);
    }

    // ------------------------------------------------------------------
    // M4 / M5 : fuseau et copies
    // ------------------------------------------------------------------

    public function test_no_show_notifications_print_the_business_timezone(): void
    {
        $booking = $this->makeBooking(BookingStatus::Paid);

        $this->as($this->producerUser)->postJson("/api/v1/bookings/{$booking->uuid}/report-no-show")->assertOk();

        $expected = $this->local(now()->addHours(72));
        $this->assertSame('13/10/2026 13:00', $expected);
        foreach ([$this->faceUser, $this->producerUser] as $user) {
            $message = Notification::query()->where('user_id', $user->id)->where('type', 'booking_no_show')->firstOrFail()->data['message'];
            $this->assertStringContainsString($expected, $message);
        }
    }

    public function test_contest_and_late_cancel_print_the_business_timezone(): void
    {
        $booking = $this->makeBooking(BookingStatus::Paid, [
            'date_debut' => now()->startOfDay(),
            'date_fin' => now()->startOfDay(),
        ]);

        $this->as($this->producerUser)->postJson("/api/v1/bookings/{$booking->uuid}/cancel", [
            'cancellation_reason' => 'other',
            'custom_cancellation_reason' => 'Imprévu de production.',
        ])->assertOk();

        $message = Notification::query()->where('user_id', $this->faceUser->id)->where('type', 'booking_cancelled')->firstOrFail()->data['message'];
        $this->assertStringContainsString('13/10/2026 13:00', $message);
    }

    public function test_uncontested_settlement_copy_for_each_party(): void
    {
        $this->makeBooking(BookingStatus::NoShow, ['settlement_due_at' => now()->subHour()]);

        $this->artisan('bookings:settle-disputes')->assertSuccessful();

        $producer = Notification::query()->where('user_id', $this->producerUser->id)->where('type', 'booking_dispute_settled')->firstOrFail()->data['message'];
        $face = Notification::query()->where('user_id', $this->faceUser->id)->where('type', 'booking_dispute_settled')->firstOrFail()->data['message'];

        $this->assertStringContainsString('Aucune contestation de la Face : vous avez été remboursé de', $producer);
        $this->assertStringContainsString('Délai de contestation écoulé : le Producteur a été remboursé.', $face);
    }

    public function test_contested_settlement_copy_names_the_administrator(): void
    {
        $booking = $this->makeBooking(BookingStatus::NoShow, [
            'settlement_due_at' => now()->addHours(10),
            'disputed_at' => now()->subHour(),
        ]);
        $admin = Admin::factory()->create(['role' => AdminRole::Admin]);

        $this->as($admin)->postJson("/api/v1/admin/booking-disputes/{$booking->uuid}/resolve", [
            'outcome' => 'favor_face',
            'notes' => 'Preuves fournies.',
        ])->assertOk();

        $producer = Notification::query()->where('user_id', $this->producerUser->id)->where('type', 'booking_dispute_settled')->firstOrFail()->data['message'];
        $face = Notification::query()->where('user_id', $this->faceUser->id)->where('type', 'booking_dispute_settled')->firstOrFail()->data['message'];

        $this->assertStringContainsString('L\'administrateur a tranché en faveur de la Face', $producer);
        $this->assertStringContainsString('L\'administrateur a tranché en votre faveur', $face);
    }

    // ------------------------------------------------------------------
    // L1 / L2
    // ------------------------------------------------------------------

    public function test_contest_authorization_runs_before_validation(): void
    {
        $booking = $this->makeBooking(BookingStatus::NoShow, ['settlement_due_at' => now()->addHours(10)]);

        $this->as($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/contest", ['message' => 'x'])
            ->assertForbidden();
    }

    public function test_stale_paid_rows_expose_the_auto_completion_deadline_and_legacy_flag(): void
    {
        $legacy = $this->makeBooking(BookingStatus::Paid, [
            'date_debut' => now()->subDays(60),
            'date_fin' => now()->subDays(60),
        ]);
        $reminded = $this->makeBooking(BookingStatus::Paid, [
            'date_debut' => now()->subDays(10),
            'date_fin' => now()->subDays(10),
            'completion_reminder_sent_at' => now()->subDay(),
        ]);
        $notReminded = $this->makeBooking(BookingStatus::Paid, [
            'date_debut' => now()->subDays(9),
            'date_fin' => now()->subDays(9),
        ]);
        $admin = Admin::factory()->create(['role' => AdminRole::Admin]);

        $rows = collect($this->as($admin)->getJson('/api/v1/admin/booking-disputes')->assertOk()->json('data.stale_paid'))->keyBy('id');

        $this->assertTrue($rows[$legacy->uuid]['is_legacy']);
        $this->assertNull($rows[$legacy->uuid]['auto_complete_due_at']);
        $this->assertFalse($rows[$reminded->uuid]['is_legacy']);
        $this->assertSame(
            now()->subDay()->addDays(6)->toIso8601String(),
            Carbon::parse($rows[$reminded->uuid]['auto_complete_due_at'])->toIso8601String(),
        );
        $this->assertNull($rows[$notReminded->uuid]['auto_complete_due_at']);
    }
}
