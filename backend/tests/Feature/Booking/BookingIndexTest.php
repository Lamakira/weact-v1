<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingIndexTest extends TestCase
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

        $this->face = Face::factory()->create([
            'tarif_horaire' => 5000,
            'tarif_journalier' => 30000,
            'is_available' => true,
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    // === ACCESS TESTS ===

    public function test_unauthenticated_user_gets_401(): void
    {
        $this->getJson('/api/v1/bookings')
            ->assertUnauthorized();
    }

    // === FACE LIST TESTS ===

    public function test_face_can_list_their_own_bookings(): void
    {
        // Create 3 bookings for this face
        Booking::factory()->count(3)->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        // Create 2 bookings for another face (should NOT appear)
        $otherFaceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => Face::factory(),
        ]);
        Booking::factory()->count(2)->create([
            'face_id' => $otherFaceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/bookings');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    // === PRODUCER LIST TESTS ===

    public function test_producer_can_list_their_own_bookings(): void
    {
        // Create 2 bookings for this producer
        Booking::factory()->count(2)->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        // Create 3 bookings for another producer (should NOT appear)
        $otherProducerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => Producer::factory(),
        ]);
        Booking::factory()->count(3)->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $otherProducerUser->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/bookings');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    // === STATUS FILTER TESTS ===

    public function test_status_filter_pending_returns_correct_subset(): void
    {
        Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);
        Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);
        Booking::factory()->completed()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/bookings?status=pending');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', BookingStatus::Pending->value);
    }

    public function test_status_filter_active_returns_correct_subset(): void
    {
        Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);
        Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);
        Booking::factory()->paid()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);
        Booking::factory()->completed()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/bookings?status=active');

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        $statuses = collect($response->json('data'))->pluck('status')->all();
        $this->assertContains(BookingStatus::Accepted->value, $statuses);
        $this->assertContains(BookingStatus::Paid->value, $statuses);
    }

    public function test_status_filter_completed_returns_correct_subset(): void
    {
        Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);
        Booking::factory()->completed()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/bookings?status=completed');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', BookingStatus::Completed->value);
    }

    public function test_status_filter_cancelled_returns_correct_subset(): void
    {
        Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);
        Booking::factory()->refused()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);
        Booking::factory()->cancelledByProducer()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/bookings?status=cancelled');

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        $statuses = collect($response->json('data'))->pluck('status')->all();
        $this->assertContains(BookingStatus::Refused->value, $statuses);
        $this->assertContains(BookingStatus::CancelledByProducer->value, $statuses);
    }

    public function test_invalid_status_filter_returns_all_bookings(): void
    {
        Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);
        Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);
        Booking::factory()->completed()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/bookings?status=invalid_garbage');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    // === ORDERING TESTS ===

    public function test_bookings_are_ordered_by_updated_at_desc(): void
    {
        $oldest = Booking::factory()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'updated_at' => now()->subDays(3),
        ]);
        $middle = Booking::factory()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'updated_at' => now()->subDays(1),
        ]);
        $newest = Booking::factory()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/bookings');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertEquals([$newest->id, $middle->id, $oldest->id], $ids);
    }

    // === PAGINATION TESTS ===

    public function test_pagination_returns_correct_meta(): void
    {
        Booking::factory()->count(20)->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/bookings');

        $response->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 20);
    }

    public function test_empty_list_returns_empty_data_array(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/bookings');

        $response->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    // === RESPONSE STRUCTURE TESTS ===

    public function test_response_includes_nested_face_producer_user_data(): void
    {
        Booking::factory()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/bookings');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'face_id',
                        'producer_id',
                        'status',
                        'status_label',
                        'date_debut',
                        'date_fin',
                        'duree_heures',
                        'type_contenu',
                        'tarif_base',
                        'montant_total_producteur',
                        'montant_face_recoit',
                        'face' => ['id', 'userable'],
                        'producer' => ['id', 'userable'],
                        'can_accept',
                        'can_refuse',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'links',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }
}
