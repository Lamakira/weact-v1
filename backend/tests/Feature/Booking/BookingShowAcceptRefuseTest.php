<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Events\BookingAccepted;
use App\Events\BookingRefused;
use App\Models\Booking;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BookingShowAcceptRefuseTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    private User $faceUser;

    private Face $face;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);

        $this->face = Face::factory()->create([
            'tarif_horaire' => 5000,
            'tarif_journalier' => 30000,
            'is_available' => true,
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);

        $this->booking = Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);
    }

    // === SHOW TESTS ===

    public function test_unauthenticated_user_cannot_view_booking(): void
    {
        $this->getJson("/api/v1/bookings/{$this->booking->id}")
            ->assertUnauthorized();
    }

    public function test_face_can_view_their_own_booking_detail(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$this->booking->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $this->booking->id)
            ->assertJsonPath('data.status', BookingStatus::Pending->value);
    }

    public function test_producer_can_view_their_own_booking_detail(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/bookings/{$this->booking->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $this->booking->id);
    }

    public function test_unauthorized_user_cannot_view_booking(): void
    {
        $otherUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => Face::factory(),
        ]);

        $response = $this->actingAs($otherUser)
            ->getJson("/api/v1/bookings/{$this->booking->id}");

        $response->assertForbidden();
    }

    public function test_booking_show_returns_correct_resource_structure(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$this->booking->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'face_id',
                    'producer_id',
                    'status',
                    'status_label',
                    'date_debut',
                    'date_fin',
                    'duree_heures',
                    'type_contenu',
                    'message',
                    'tarif_base',
                    'montant_total_producteur',
                    'montant_face_recoit',
                    'cancellation_reason',
                    'face',
                    'producer',
                    'can_accept',
                    'can_refuse',
                    'created_at',
                    'updated_at',
                ],
                'message',
            ]);
    }

    // === ACCEPT TESTS ===

    public function test_face_can_accept_pending_booking(): void
    {
        Event::fake([BookingAccepted::class]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$this->booking->id}/accept");

        $response->assertOk()
            ->assertJsonPath('data.status', BookingStatus::Accepted->value)
            ->assertJsonPath('message', 'Booking accepté');

        $this->assertDatabaseHas('bookings', [
            'id' => $this->booking->id,
            'status' => BookingStatus::Accepted->value,
        ]);

        Event::assertDispatched(BookingAccepted::class);
    }

    public function test_face_cannot_accept_non_pending_booking(): void
    {
        $this->booking->update(['status' => BookingStatus::Accepted]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$this->booking->id}/accept");

        // Policy rejects because status is not pending
        $response->assertForbidden();
    }

    public function test_producer_cannot_accept_a_booking(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$this->booking->id}/accept");

        $response->assertForbidden();
    }

    public function test_booking_accepted_event_is_dispatched(): void
    {
        Event::fake([BookingAccepted::class]);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$this->booking->id}/accept");

        Event::assertDispatched(BookingAccepted::class, function (BookingAccepted $event) {
            return $event->booking->id === $this->booking->id;
        });
    }

    // === REFUSE TESTS ===

    public function test_face_can_refuse_pending_booking(): void
    {
        Event::fake([BookingRefused::class]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$this->booking->id}/refuse");

        $response->assertOk()
            ->assertJsonPath('data.status', BookingStatus::Refused->value)
            ->assertJsonPath('message', 'Booking refusé');

        $this->assertDatabaseHas('bookings', [
            'id' => $this->booking->id,
            'status' => BookingStatus::Refused->value,
        ]);

        Event::assertDispatched(BookingRefused::class);
    }

    public function test_face_refuses_paid_booking_requires_cancellation_reason(): void
    {
        $this->booking->update(['status' => BookingStatus::Paid]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$this->booking->id}/refuse", [
                'cancellation_reason' => 'Empêchement personnel',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', BookingStatus::Refused->value)
            ->assertJsonPath('data.cancellation_reason', 'Empêchement personnel');
    }

    public function test_face_refuses_paid_booking_without_reason_returns_422(): void
    {
        $this->booking->update(['status' => BookingStatus::Paid]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$this->booking->id}/refuse");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('cancellation_reason');
    }

    public function test_booking_refused_event_is_dispatched(): void
    {
        Event::fake([BookingRefused::class]);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$this->booking->id}/refuse");

        Event::assertDispatched(BookingRefused::class, function (BookingRefused $event) {
            return $event->booking->id === $this->booking->id;
        });
    }
}
