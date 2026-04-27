<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Enums\FinancialEventType;
use App\Events\BookingNoShowReported;
use App\Models\Booking;
use App\Models\EscrowTransaction;
use App\Models\Face;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingNoShowTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    private User $faceUser;

    private Face $face;

    private function withApiToken(User $user): static
    {
        return $this->withToken($user->createToken('test-token')->plainTextToken);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);

        $this->face = Face::factory()->create(['rating_penalty' => 0.0]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    public function test_producer_can_report_no_show_on_paid_booking_with_past_date(): void
    {
        Event::fake([BookingNoShowReported::class]);

        $booking = Booking::factory()->paid()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->subDay(),
            'montant_total_producteur' => 50000,
        ]);

        EscrowTransaction::factory()->create([
            'booking_id' => $booking->id,
            'amount' => $booking->montant_face_recoit,
            'status' => 'locked',
        ]);

        $response = $this->withApiToken($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/report-no-show");

        $response->assertOk()
            ->assertJsonPath('data.status', BookingStatus::NoShow->value);

        // Status updated
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::NoShow->value,
        ]);

        // Wallet credited with 100% of montant_total_producteur
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $this->producerUser->id,
            'booking_id' => $booking->id,
            'type' => 'credit',
            'amount' => 50000,
            'description' => 'Booking : remboursement absence Face (100%)',
        ]);

        // Producer balance updated
        $this->producerUser->refresh();
        $this->assertEquals(50000, $this->producerUser->balance);

        // Escrow marked as refunded
        $this->assertDatabaseHas('escrow_transactions', [
            'booking_id' => $booking->id,
            'status' => 'refunded',
        ]);

        // Financial event recorded
        $this->assertDatabaseHas('financial_events', [
            'booking_id' => $booking->id,
            'type' => FinancialEventType::Refund->value,
            'amount' => 50000,
        ]);

        // Rating penalty applied
        $this->face->refresh();
        $this->assertEquals(1.0, $this->face->rating_penalty);

        Event::assertDispatched(BookingNoShowReported::class);
    }

    public function test_no_show_fails_if_booking_is_not_paid(): void
    {
        $booking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->subDay(),
        ]);

        $response = $this->withApiToken($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/report-no-show");

        $response->assertUnprocessable();
    }

    public function test_no_show_fails_if_date_debut_is_in_the_future(): void
    {
        $booking = Booking::factory()->paid()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->addDays(3),
        ]);

        $response = $this->withApiToken($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/report-no-show");

        $response->assertUnprocessable();
    }

    public function test_face_cannot_report_no_show(): void
    {
        $booking = Booking::factory()->paid()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->subDay(),
        ]);

        $response = $this->withApiToken($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/report-no-show");

        $response->assertForbidden();
    }

    public function test_no_show_is_idempotent_cannot_report_twice(): void
    {
        Event::fake([BookingNoShowReported::class]);

        $booking = Booking::factory()->paid()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->subDay(),
            'montant_total_producteur' => 50000,
        ]);

        EscrowTransaction::factory()->create([
            'booking_id' => $booking->id,
            'amount' => $booking->montant_face_recoit,
            'status' => 'locked',
        ]);

        // First report succeeds
        $this->withApiToken($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/report-no-show")
            ->assertOk();

        // Second report fails (status is now no_show, not paid)
        $response = $this->withApiToken($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/report-no-show");

        $response->assertUnprocessable();
    }

    public function test_no_show_fails_if_paid_booking_has_no_escrow_transaction(): void
    {
        $booking = Booking::factory()->paid()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->subDay(),
            'montant_total_producteur' => 50000,
        ]);

        $response = $this->withApiToken($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/report-no-show");

        $response->assertStatus(500);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Paid->value,
        ]);

        $this->assertDatabaseMissing('wallet_transactions', [
            'user_id' => $this->producerUser->id,
            'booking_id' => $booking->id,
            'type' => 'credit',
            'amount' => 50000,
        ]);

        $this->assertDatabaseMissing('financial_events', [
            'booking_id' => $booking->id,
            'type' => FinancialEventType::Refund->value,
            'amount' => 50000,
        ]);

        $this->producerUser->refresh();
        $this->assertEquals(0, $this->producerUser->balance);
    }

    public function test_no_show_creates_notifications_for_both_parties(): void
    {
        $booking = Booking::factory()->paid()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->subDay(),
            'montant_total_producteur' => 50000,
        ]);

        EscrowTransaction::factory()->create([
            'booking_id' => $booking->id,
            'amount' => $booking->montant_face_recoit,
            'status' => 'locked',
        ]);

        $this->withApiToken($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/report-no-show")
            ->assertOk();

        $this->assertTrue(
            Notification::query()
                ->where('user_id', $this->producerUser->id)
                ->where('type', 'booking_no_show')
                ->where('data->booking_id', $booking->id)
                ->exists()
        );

        $this->assertTrue(
            Notification::query()
                ->where('user_id', $this->faceUser->id)
                ->where('type', 'booking_no_show')
                ->where('data->booking_id', $booking->id)
                ->exists()
        );
    }

    public function test_report_no_show_e2e_dispatches_wallet_credited_email_to_producer(): void
    {
        Mail::fake();
        // PAS de Event::fake — on veut que les listeners s'exécutent réellement.

        $booking = Booking::factory()->paid()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->subDay(),
            'montant_total_producteur' => 50000,
        ]);

        EscrowTransaction::factory()->create([
            'booking_id' => $booking->id,
            'amount' => $booking->montant_face_recoit,
            'status' => 'locked',
        ]);

        $response = $this->withApiToken($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/report-no-show");

        $response->assertOk();

        Mail::assertQueued(
            \App\Mail\WalletCreditedMail::class,
            fn (\App\Mail\WalletCreditedMail $mail): bool => $mail->hasTo($this->producerUser->email)
                && $mail->amount === 50000
                && $mail->motif === \App\Enums\WalletCreditMotif::BookingNoShowRefund,
        );
    }
}
