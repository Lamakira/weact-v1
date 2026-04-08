<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Enums\FinancialEventType;
use App\Events\BookingCancelled;
use App\Mail\BookingCancelledMail;
use App\Models\Booking;
use App\Models\EscrowTransaction;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use App\Services\FedapayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingCancellationTest extends TestCase
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

        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    public function test_producer_can_cancel_pending_booking_without_financial_impact(): void
    {
        Event::fake([BookingCancelled::class]);

        $booking = Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->producerUser)->withApiToken($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", [
                'cancellation_reason' => 'schedule_conflict',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CancelledByProducer->value)
            ->assertJsonPath('data.cancellation_reason', 'schedule_conflict');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::CancelledByProducer->value,
            'cancellation_reason' => 'schedule_conflict',
        ]);

        $this->assertDatabaseMissing('financial_events', [
            'booking_id' => $booking->id,
            'type' => FinancialEventType::Refund->value,
        ]);

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'rating_penalty' => 0.0,
        ]);

        Event::assertDispatched(BookingCancelled::class);
    }

    public function test_producer_can_cancel_accepted_booking_without_financial_impact(): void
    {
        Event::fake([BookingCancelled::class]);

        $booking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->producerUser)->withApiToken($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", [
                'cancellation_reason' => 'acceptance_expired',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CancelledByProducer->value)
            ->assertJsonPath('data.cancellation_reason', 'acceptance_expired');

        $this->assertDatabaseMissing('financial_events', [
            'booking_id' => $booking->id,
            'type' => FinancialEventType::Refund->value,
        ]);

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'rating_penalty' => 0.0,
        ]);

        Event::assertDispatched(BookingCancelled::class);
    }

    public function test_producer_can_cancel_paid_booking_and_trigger_refund(): void
    {
        Event::fake([BookingCancelled::class]);

        $booking = Booking::factory()->paid()->withFedapayTransaction()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        EscrowTransaction::factory()->create([
            'booking_id' => $booking->id,
            'amount' => $booking->montant_face_recoit,
            'status' => 'locked',
        ]);

        $expectedRefund = (int) round($booking->montant_total_producteur * 0.90);

        $this->mock(FedapayService::class, function ($mock) use ($booking, $expectedRefund): void {
            $mock->shouldReceive('initiateRefund')
                ->once()
                ->withArgs(function (Booking $passedBooking, int $amount) use ($booking, $expectedRefund): bool {
                    return $passedBooking->id === $booking->id && $amount === $expectedRefund;
                })
                ->andReturn([
                    'fedapay_refund_id' => 654321,
                    'status' => 'approved',
                ]);
        });

        $response = $this->actingAs($this->producerUser)->withApiToken($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", [
                'cancellation_reason' => 'other',
                'custom_cancellation_reason' => 'La production a été reportée.',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CancelledByProducer->value)
            ->assertJsonPath('data.cancellation_reason', 'other');

        $this->assertDatabaseHas('escrow_transactions', [
            'booking_id' => $booking->id,
            'status' => 'refunded',
            'fedapay_ref' => '654321',
        ]);

        $this->assertDatabaseHas('financial_events', [
            'booking_id' => $booking->id,
            'type' => FinancialEventType::Refund->value,
            'amount' => $expectedRefund,
            'status' => 'approved',
            'fedapay_ref' => '654321',
        ]);

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'rating_penalty' => 0.0,
        ]);

        Event::assertDispatched(BookingCancelled::class);
    }

    public function test_paid_booking_refund_stays_pending_until_provider_settlement(): void
    {
        $booking = Booking::factory()->paid()->withFedapayTransaction()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        EscrowTransaction::factory()->create([
            'booking_id' => $booking->id,
            'amount' => $booking->montant_face_recoit,
            'status' => 'locked',
        ]);

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('initiateRefund')
                ->once()
                ->andReturn([
                    'fedapay_refund_id' => 777888,
                    'status' => 'pending',
                ]);
        });

        $this->actingAs($this->producerUser)->withApiToken($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", [
                'cancellation_reason' => 'other',
                'custom_cancellation_reason' => 'La production a été reportée.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('escrow_transactions', [
            'booking_id' => $booking->id,
            'status' => 'pending',
            'fedapay_ref' => '777888',
        ]);
    }

    public function test_face_can_cancel_accepted_booking_and_get_penalty(): void
    {
        Event::fake([BookingCancelled::class]);

        $booking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->faceUser)->withApiToken($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", [
                'cancellation_reason' => 'schedule_conflict',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CancelledByFace->value)
            ->assertJsonPath('data.cancellation_reason', 'schedule_conflict');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::CancelledByFace->value,
            'cancellation_reason' => 'schedule_conflict',
        ]);

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'rating_penalty' => 1.0,
        ]);

        Event::assertDispatched(BookingCancelled::class);
    }

    public function test_producer_cancellation_sends_email_to_face(): void
    {
        Mail::fake();

        $booking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $this->actingAs($this->producerUser)->withApiToken($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", [
                'cancellation_reason' => 'acceptance_expired',
            ])
            ->assertOk();

        $expectedDate = $booking->fresh()->updated_at?->format('d/m/Y H:i');

        Mail::assertQueued(BookingCancelledMail::class, function (BookingCancelledMail $mail) use ($booking, $expectedDate): bool {
            return $mail->hasTo($this->faceUser->email)
                && $mail->cancelledBy === 'producer'
                && $mail->booking->id === $booking->id
                && $mail->envelope()->subject === 'Votre demande de booking a été annulée'
                && ! str_contains($mail->render(), 'Le booking <strong>#')
                && ($expectedDate === null || str_contains($mail->render(), $expectedDate));
        });
    }

    public function test_face_cancellation_sends_email_to_producer(): void
    {
        Mail::fake();

        $booking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $this->actingAs($this->faceUser)->withApiToken($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", [
                'cancellation_reason' => 'schedule_conflict',
            ])
            ->assertOk();

        $expectedDate = $booking->fresh()->updated_at?->format('d/m/Y H:i');

        Mail::assertQueued(BookingCancelledMail::class, function (BookingCancelledMail $mail) use ($booking, $expectedDate): bool {
            return $mail->hasTo($this->producerUser->email)
                && $mail->cancelledBy === 'face'
                && $mail->booking->id === $booking->id
                && $mail->envelope()->subject === 'Votre demande de booking a été annulée'
                && ! str_contains($mail->render(), 'Le booking <strong>#')
                && ($expectedDate === null || str_contains($mail->render(), $expectedDate));
        });
    }

    public function test_cancellation_with_other_reason_stores_custom_reason_and_exposes_it(): void
    {
        Mail::fake();

        $booking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->producerUser)->withApiToken($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", [
                'cancellation_reason' => 'other',
                'custom_cancellation_reason' => 'Le client final a reporté la campagne.',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CancelledByProducer->value)
            ->assertJsonPath('data.cancellation_reason', 'other')
            ->assertJsonPath('data.custom_cancellation_reason', 'Le client final a reporté la campagne.');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'cancellation_reason' => 'other',
            'custom_cancellation_reason' => 'Le client final a reporté la campagne.',
        ]);

        Mail::assertQueued(BookingCancelledMail::class, function (BookingCancelledMail $mail): bool {
            return str_contains($mail->render(), 'Le client final a reporté la campagne.');
        });
    }

    public function test_legacy_price_disagreement_reason_keeps_human_readable_label_in_email(): void
    {
        Mail::fake();

        $booking = Booking::factory()->cancelledByProducer()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'cancellation_reason' => 'price_disagreement',
        ]);

        Mail::to($this->faceUser->email)->queue(new BookingCancelledMail($booking, 'producer'));

        Mail::assertQueued(BookingCancelledMail::class, function (BookingCancelledMail $mail): bool {
            $rendered = $mail->render();

            return str_contains($rendered, 'Désaccord sur le prix')
                && ! str_contains($rendered, 'price_disagreement');
        });
    }

    public function test_face_cannot_cancel_pending_booking(): void
    {
        $booking = Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->faceUser)->withApiToken($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", [
                'cancellation_reason' => 'schedule_conflict',
            ]);

        $response->assertForbidden();
    }

    public function test_cancel_requires_reason(): void
    {
        $booking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->producerUser)->withApiToken($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('cancellation_reason');
    }

    public function test_cancel_with_invalid_reason_returns_422(): void
    {
        $booking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->producerUser)->withApiToken($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", [
                'cancellation_reason' => 'invalid_reason',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('cancellation_reason');
    }

    public function test_cancel_with_other_reason_requires_custom_reason(): void
    {
        $booking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->producerUser)->withApiToken($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", [
                'cancellation_reason' => 'other',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('custom_cancellation_reason');
    }

    public function test_cancel_with_non_other_reason_ignores_custom_reason(): void
    {
        $booking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->producerUser)->withApiToken($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", [
                'cancellation_reason' => 'schedule_conflict',
                'custom_cancellation_reason' => 'This should be ignored.',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.cancellation_reason', 'schedule_conflict')
            ->assertJsonPath('data.custom_cancellation_reason', null);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'cancellation_reason' => 'schedule_conflict',
            'custom_cancellation_reason' => null,
        ]);
    }

    public function test_cancel_with_other_reason_rejects_whitespace_only_custom_reason(): void
    {
        $booking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->producerUser)->withApiToken($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", [
                'cancellation_reason' => 'other',
                'custom_cancellation_reason' => '   ',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('custom_cancellation_reason');
    }

    public function test_producer_cannot_cancel_completed_booking(): void
    {
        $booking = Booking::factory()->completed()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->producerUser)->withApiToken($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", [
                'cancellation_reason' => 'other',
                'custom_cancellation_reason' => 'Annulation déjà effectuée.',
            ]);

        $response->assertForbidden();
    }

    public function test_second_cancel_on_already_cancelled_booking_is_forbidden(): void
    {
        $booking = Booking::factory()->cancelledByProducer()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->producerUser)->withApiToken($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", [
                'cancellation_reason' => 'other',
                'custom_cancellation_reason' => 'Annulation déjà effectuée.',
            ]);

        $response->assertForbidden();
    }
}
