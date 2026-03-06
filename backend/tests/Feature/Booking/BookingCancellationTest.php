<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Enums\FinancialEventType;
use App\Events\BookingCancelled;
use App\Models\Booking;
use App\Models\EscrowTransaction;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use App\Services\FedapayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BookingCancellationTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    private User $faceUser;

    private Face $face;

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

        $response = $this->actingAs($this->producerUser)
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

        Event::assertDispatched(BookingCancelled::class);
    }

    public function test_producer_can_cancel_accepted_booking_without_financial_impact(): void
    {
        Event::fake([BookingCancelled::class]);

        $booking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", [
                'cancellation_reason' => 'price_disagreement',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CancelledByProducer->value)
            ->assertJsonPath('data.cancellation_reason', 'price_disagreement');

        $this->assertDatabaseMissing('financial_events', [
            'booking_id' => $booking->id,
            'type' => FinancialEventType::Refund->value,
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

        $expectedRefund = (int) round($booking->montant_total_producteur * 0.85);

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

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", [
                'cancellation_reason' => 'other',
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

        Event::assertDispatched(BookingCancelled::class);
    }

    public function test_face_cannot_cancel_booking(): void
    {
        $booking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->faceUser)
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

        $response = $this->actingAs($this->producerUser)
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

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", [
                'cancellation_reason' => 'invalid_reason',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('cancellation_reason');
    }

    public function test_producer_cannot_cancel_completed_booking(): void
    {
        $booking = Booking::factory()->completed()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", [
                'cancellation_reason' => 'other',
            ]);

        $response->assertForbidden();
    }

    public function test_second_cancel_on_already_cancelled_booking_is_forbidden(): void
    {
        $booking = Booking::factory()->cancelledByProducer()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", [
                'cancellation_reason' => 'other',
            ]);

        $response->assertForbidden();
    }
}
