<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingRating;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingRatingTest extends TestCase
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

    public function test_face_can_rate_producer_after_completed_booking(): void
    {
        $booking = Booking::factory()->completed()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/rate", [
                'score' => 5,
                'comment' => 'Excellent producteur',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.booking_id', $booking->id)
            ->assertJsonPath('data.score', 5)
            ->assertJsonPath('data.rater.id', $this->faceUser->id)
            ->assertJsonPath('data.rated.id', $this->producerUser->id);

        $this->assertDatabaseHas('booking_ratings', [
            'booking_id' => $booking->id,
            'rater_id' => $this->faceUser->id,
            'rated_id' => $this->producerUser->id,
            'score' => 5,
        ]);
    }

    public function test_producer_can_rate_face_after_completed_booking(): void
    {
        $booking = Booking::factory()->completed()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/rate", [
                'score' => 4,
                'comment' => 'Talent fiable',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.booking_id', $booking->id)
            ->assertJsonPath('data.score', 4)
            ->assertJsonPath('data.rater.id', $this->producerUser->id)
            ->assertJsonPath('data.rated.id', $this->faceUser->id);

        $this->assertDatabaseHas('booking_ratings', [
            'booking_id' => $booking->id,
            'rater_id' => $this->producerUser->id,
            'rated_id' => $this->faceUser->id,
            'score' => 4,
        ]);
    }

    public function test_rating_returns_403_when_booking_is_not_completed(): void
    {
        $booking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/rate", [
                'score' => 5,
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'BOOKING_RATING_NOT_ALLOWED');
    }

    public function test_rating_returns_422_when_user_already_rated(): void
    {
        $booking = Booking::factory()->completed()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        BookingRating::create([
            'booking_id' => $booking->id,
            'rater_id' => $this->producerUser->id,
            'rated_id' => $this->faceUser->id,
            'score' => 3,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/rate", [
                'score' => 5,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'ALREADY_RATED');
    }

    public function test_face_average_rating_includes_booking_ratings(): void
    {
        $booking = Booking::factory()->completed()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/rate", [
                'score' => 5,
            ])
            ->assertCreated();

        $face = $this->face->fresh();

        $this->assertSame(5.0, $face->average_rating);
        $this->assertSame(1, $face->ratings_count);
    }

    public function test_face_cancel_triggers_rating_penalty(): void
    {
        $booking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/cancel", [
                'cancellation_reason' => 'schedule_conflict',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CancelledByFace->value);

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'rating_penalty' => 1.0,
        ]);
    }

    public function test_producer_cancel_does_not_apply_face_penalty(): void
    {
        $booking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/cancel", [
                'cancellation_reason' => 'schedule_conflict',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CancelledByProducer->value);

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'rating_penalty' => 0.0,
        ]);
    }

    public function test_booking_detail_exposes_can_rate_and_my_rating(): void
    {
        $booking = Booking::factory()->completed()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $firstResponse = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}");

        $firstResponse->assertOk()
            ->assertJsonPath('data.can_rate', true)
            ->assertJsonPath('data.my_rating', null);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/rate", [
                'score' => 4,
                'comment' => 'Très bien',
            ])
            ->assertCreated();

        $secondResponse = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}");

        $secondResponse->assertOk()
            ->assertJsonPath('data.can_rate', false)
            ->assertJsonPath('data.my_rating.score', 4)
            ->assertJsonPath('data.my_rating.comment', 'Très bien');
    }
}
