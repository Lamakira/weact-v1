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
use Carbon\CarbonInterface;
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
        $this->getJson("/api/v1/bookings/{$this->booking->uuid}")
            ->assertUnauthorized();
    }

    public function test_face_can_view_their_own_booking_detail(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$this->booking->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.id', $this->booking->uuid)
            ->assertJsonPath('data.realtime_channel_key', $this->booking->id)
            ->assertJsonPath('data.status', BookingStatus::Pending->value);
    }

    public function test_producer_can_view_their_own_booking_detail(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/bookings/{$this->booking->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.id', $this->booking->uuid);
    }

    public function test_numeric_booking_route_no_longer_resolves(): void
    {
        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/bookings/{$this->booking->id}")
            ->assertNotFound();
    }

    public function test_unauthorized_user_cannot_view_booking(): void
    {
        $otherUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => Face::factory(),
        ]);

        $response = $this->actingAs($otherUser)
            ->getJson("/api/v1/bookings/{$this->booking->uuid}");

        $response->assertForbidden();
    }

    public function test_booking_show_returns_correct_resource_structure(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$this->booking->uuid}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'realtime_channel_key',
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
            ->postJson("/api/v1/bookings/{$this->booking->uuid}/accept");

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
            ->postJson("/api/v1/bookings/{$this->booking->uuid}/accept");

        // Policy rejects because status is not pending
        $response->assertForbidden();
    }

    public function test_producer_cannot_accept_a_booking(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$this->booking->uuid}/accept");

        $response->assertForbidden();
    }

    public function test_booking_accepted_event_is_dispatched(): void
    {
        Event::fake([BookingAccepted::class]);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$this->booking->uuid}/accept");

        Event::assertDispatched(BookingAccepted::class, function (BookingAccepted $event) {
            return $event->booking->id === $this->booking->id;
        });
    }

    // === ACCEPT OVERLAP TESTS ===

    /**
     * Crée un second booking Pending pour la même Face (autre Producteur) sur une plage donnée.
     */
    private function makePendingOnRange(CarbonInterface $start, CarbonInterface $end): Booking
    {
        return Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'date_debut' => $start,
            'date_fin' => $end,
        ]);
    }

    public function test_face_cannot_accept_second_booking_overlapping_an_accepted_one(): void
    {
        $start = now()->addDays(10)->startOfDay();
        $this->booking->update(['status' => BookingStatus::Accepted, 'date_debut' => $start, 'date_fin' => $start->copy()->addDays(2)]);
        $other = $this->makePendingOnRange($start->copy()->addDay(), $start->copy()->addDays(3));

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$other->uuid}/accept")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_debut']);

        $this->assertDatabaseHas('bookings', ['id' => $other->id, 'status' => BookingStatus::Pending->value]);
    }

    public function test_face_can_accept_booking_on_non_overlapping_dates(): void
    {
        $start = now()->addDays(10)->startOfDay();
        $this->booking->update(['status' => BookingStatus::Accepted, 'date_debut' => $start, 'date_fin' => $start->copy()->addDays(2)]);
        $other = $this->makePendingOnRange($start->copy()->addDays(5), $start->copy()->addDays(6));

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$other->uuid}/accept")
            ->assertOk();

        $this->assertDatabaseHas('bookings', ['id' => $other->id, 'status' => BookingStatus::Accepted->value]);
    }

    public function test_face_can_accept_overlapping_booking_when_previous_one_was_cancelled(): void
    {
        $start = now()->addDays(10)->startOfDay();
        $end = $start->copy()->addDays(2);
        $this->booking->update(['status' => BookingStatus::CancelledByProducer, 'date_debut' => $start, 'date_fin' => $end]);
        Booking::factory()->refused()->create(['face_id' => $this->faceUser->id, 'date_debut' => $start, 'date_fin' => $end]);
        Booking::factory()->create(['face_id' => $this->faceUser->id, 'status' => BookingStatus::Expired, 'date_debut' => $start, 'date_fin' => $end]);
        $other = $this->makePendingOnRange($start->copy()->addDay(), $start->copy()->addDays(3));

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$other->uuid}/accept")
            ->assertOk();
    }

    public function test_overlap_boundary_same_day_counts_as_overlap(): void
    {
        $start = now()->addDays(10)->startOfDay();
        $this->booking->update(['status' => BookingStatus::Paid, 'date_debut' => $start, 'date_fin' => $start->copy()->addDays(2)]);
        // B commence le jour où A se termine (plage inclusive).
        $other = $this->makePendingOnRange($start->copy()->addDays(2), $start->copy()->addDays(4));

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$other->uuid}/accept")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_debut']);
    }

    // === REFUSE TESTS ===

    public function test_face_can_refuse_pending_booking(): void
    {
        Event::fake([BookingRefused::class]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$this->booking->uuid}/refuse");

        $response->assertOk()
            ->assertJsonPath('data.status', BookingStatus::Refused->value)
            ->assertJsonPath('message', 'Booking refusé');

        $this->assertDatabaseHas('bookings', [
            'id' => $this->booking->id,
            'status' => BookingStatus::Refused->value,
        ]);

        Event::assertDispatched(BookingRefused::class);
    }

    public function test_face_cannot_refuse_paid_booking(): void
    {
        $this->booking->update(['status' => BookingStatus::Paid]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$this->booking->uuid}/refuse", [
                'cancellation_reason' => 'Empêchement personnel',
            ]);

        $response->assertForbidden();
    }

    public function test_face_refusing_paid_booking_without_reason_is_still_forbidden(): void
    {
        $this->booking->update(['status' => BookingStatus::Paid]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$this->booking->uuid}/refuse");

        $response->assertForbidden();
    }

    public function test_booking_refused_event_is_dispatched(): void
    {
        Event::fake([BookingRefused::class]);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$this->booking->uuid}/refuse");

        Event::assertDispatched(BookingRefused::class, function (BookingRefused $event) {
            return $event->booking->id === $this->booking->id;
        });
    }
}
