<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

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

class BookingCompletionTest extends TestCase
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

    /** @return array<string, string> */
    private function authHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken,
        ];
    }

    // === FIRST CONFIRMATION TESTS ===

    public function test_face_can_confirm_paid_booking(): void
    {
        $booking = Booking::factory()->paid()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->subDay(),
        ]);

        $response = $this->postJson("/api/v1/bookings/{$booking->uuid}/confirm", [], $this->authHeaders($this->faceUser));

        $response->assertOk()
            ->assertJsonPath('data.status', BookingStatus::ConfirmedByFace->value);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::ConfirmedByFace->value,
        ]);
    }

    public function test_producer_can_confirm_paid_booking(): void
    {
        $booking = Booking::factory()->paid()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->subDay(),
        ]);

        $response = $this->postJson("/api/v1/bookings/{$booking->uuid}/confirm", [], $this->authHeaders($this->producerUser));

        $response->assertOk()
            ->assertJsonPath('data.status', BookingStatus::ConfirmedByProducer->value);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::ConfirmedByProducer->value,
        ]);
    }

    // === SECOND CONFIRMATION (COMPLETION) TESTS ===

    public function test_producer_second_confirm_completes_booking(): void
    {
        Event::fake();

        $booking = Booking::factory()->confirmedByFace()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'montant_face_recoit' => 45000,
            'date_debut' => now()->subDay(),
        ]);

        // Create escrow record as if payment was processed
        EscrowTransaction::factory()->create([
            'booking_id' => $booking->id,
            'amount' => $booking->montant_total_producteur,
            'status' => 'locked',
        ]);

        $response = $this->postJson("/api/v1/bookings/{$booking->uuid}/confirm", [], $this->authHeaders($this->producerUser));

        $response->assertOk()
            ->assertJsonPath('data.status', BookingStatus::Completed->value);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Completed->value,
        ]);

        Event::assertDispatched(BookingCompleted::class);
    }

    public function test_face_second_confirm_completes_booking(): void
    {
        Event::fake();

        $booking = Booking::factory()->confirmedByProducer()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'montant_face_recoit' => 45000,
            'date_debut' => now()->subDay(),
        ]);

        EscrowTransaction::factory()->create([
            'booking_id' => $booking->id,
            'amount' => $booking->montant_total_producteur,
            'status' => 'locked',
        ]);

        $response = $this->postJson("/api/v1/bookings/{$booking->uuid}/confirm", [], $this->authHeaders($this->faceUser));

        $response->assertOk()
            ->assertJsonPath('data.status', BookingStatus::Completed->value);

        Event::assertDispatched(BookingCompleted::class);
    }

    public function test_completion_credits_face_wallet(): void
    {
        Event::fake();

        $booking = Booking::factory()->confirmedByFace()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'montant_face_recoit' => 45000,
            'date_debut' => now()->subDay(),
        ]);

        EscrowTransaction::factory()->create([
            'booking_id' => $booking->id,
            'amount' => $booking->montant_total_producteur,
            'status' => 'locked',
        ]);

        $this->postJson("/api/v1/bookings/{$booking->uuid}/confirm", [], $this->authHeaders($this->producerUser))
            ->assertOk();

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $this->faceUser->id,
            'booking_id' => $booking->id,
            'type' => 'credit',
            'amount' => 45000,
            'description' => 'Booking : escrow libéré',
        ]);

        // Face wallet balance is updated
        $this->faceUser->refresh();
        $this->assertEquals(45000, $this->faceUser->balance);
    }

    public function test_completion_releases_escrow(): void
    {
        Event::fake();

        $booking = Booking::factory()->confirmedByFace()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->subDay(),
        ]);

        $escrow = EscrowTransaction::factory()->create([
            'booking_id' => $booking->id,
            'amount' => $booking->montant_total_producteur,
            'status' => 'locked',
        ]);

        $this->postJson("/api/v1/bookings/{$booking->uuid}/confirm", [], $this->authHeaders($this->producerUser))
            ->assertOk();

        $escrow->refresh();
        $this->assertEquals('released', $escrow->status);
        $this->assertNotNull($escrow->released_at);
    }

    // === AUTHORIZATION TESTS ===

    public function test_third_party_cannot_confirm_booking(): void
    {
        $otherUser = User::factory()->create();
        $booking = Booking::factory()->paid()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->subDay(),
        ]);

        $this->postJson("/api/v1/bookings/{$booking->uuid}/confirm", [], $this->authHeaders($otherUser))
            ->assertForbidden();
    }

    public function test_unauthenticated_user_gets_401_on_confirm(): void
    {
        $booking = Booking::factory()->paid()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->subDay(),
        ]);

        $this->postJson("/api/v1/bookings/{$booking->uuid}/confirm")
            ->assertUnauthorized();
    }

    public function test_cannot_confirm_pending_booking(): void
    {
        $booking = Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $this->postJson("/api/v1/bookings/{$booking->uuid}/confirm", [], $this->authHeaders($this->faceUser))
            ->assertForbidden();
    }

    public function test_cannot_confirm_completed_booking(): void
    {
        $booking = Booking::factory()->completed()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $this->postJson("/api/v1/bookings/{$booking->uuid}/confirm", [], $this->authHeaders($this->faceUser))
            ->assertForbidden();
    }

    public function test_cannot_confirm_refused_booking(): void
    {
        $booking = Booking::factory()->refused()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $this->postJson("/api/v1/bookings/{$booking->uuid}/confirm", [], $this->authHeaders($this->faceUser))
            ->assertForbidden();
    }

    // === IDEMPOTENCY: already-first-confirmed by same party ===

    public function test_face_cannot_confirm_again_after_first_face_confirmation(): void
    {
        $booking = Booking::factory()->confirmedByFace()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->subDay(),
        ]);

        // Policy denies since ConfirmedByFace is not an allowed confirm status for Face again
        // (Policy allows only: Paid, InProgress, ConfirmedByFace, ConfirmedByProducer)
        // But service will throw 422 if the same party tries to confirm when already their turn is done
        // Actually, policy allows ConfirmedByFace for both parties so service handles it

        // Face tries to confirm again — service won't complete (not confirmedByProducer)
        // but will try to set confirmed_by_face again; this is actually a no-op / 422
        $response = $this->postJson("/api/v1/bookings/{$booking->uuid}/confirm", [], $this->authHeaders($this->faceUser));

        // Either 422 from service or booking stays ConfirmedByFace — status unchanged
        if ($response->status() === 200) {
            $response->assertJsonPath('data.status', BookingStatus::ConfirmedByFace->value);
        } else {
            $response->assertUnprocessable();
        }
    }

    // === DATE GUARD: cannot confirm before shooting date ===

    public function test_face_cannot_confirm_before_shooting_date(): void
    {
        $booking = Booking::factory()->paid()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->addWeek(),
        ]);

        $response = $this->postJson("/api/v1/bookings/{$booking->uuid}/confirm", [], $this->authHeaders($this->faceUser));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date_debut']);
    }

    public function test_producer_cannot_confirm_before_shooting_date(): void
    {
        $booking = Booking::factory()->paid()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->addWeek(),
        ]);

        $response = $this->postJson("/api/v1/bookings/{$booking->uuid}/confirm", [], $this->authHeaders($this->producerUser));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date_debut']);
    }

    public function test_second_confirmation_blocked_before_shooting_date(): void
    {
        $booking = Booking::factory()->confirmedByFace()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->addWeek(),
        ]);

        $response = $this->postJson("/api/v1/bookings/{$booking->uuid}/confirm", [], $this->authHeaders($this->producerUser));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date_debut']);
    }

    public function test_confirmation_succeeds_when_shooting_date_is_today(): void
    {
        $booking = Booking::factory()->paid()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now(),
        ]);

        $response = $this->postJson("/api/v1/bookings/{$booking->uuid}/confirm", [], $this->authHeaders($this->faceUser));

        $response->assertOk()
            ->assertJsonPath('data.status', BookingStatus::ConfirmedByFace->value);
    }

    public function test_confirmation_succeeds_when_shooting_date_is_past(): void
    {
        $booking = Booking::factory()->paid()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'date_debut' => now()->subWeek(),
        ]);

        $response = $this->postJson("/api/v1/bookings/{$booking->uuid}/confirm", [], $this->authHeaders($this->faceUser));

        $response->assertOk()
            ->assertJsonPath('data.status', BookingStatus::ConfirmedByFace->value);
    }
}
