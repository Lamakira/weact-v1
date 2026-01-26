<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\MissionStatus;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProducerMissionsListTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create producer with user
        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    // ===== SUCCESSFUL LIST RETRIEVAL TESTS =====

    public function test_producer_can_list_own_missions(): void
    {
        // Create multiple missions for this producer
        Mission::factory()->count(3)->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/missions');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJson([
                'message' => 'Missions récupérées avec succès',
            ]);
    }

    public function test_producer_only_sees_own_missions(): void
    {
        // Create missions for this producer
        Mission::factory()->count(2)->create([
            'producer_id' => $this->producer->id,
        ]);

        // Create missions for another producer
        $otherProducer = Producer::factory()->create();
        Mission::factory()->count(3)->create([
            'producer_id' => $otherProducer->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/missions');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_empty_list_returns_appropriate_message(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/missions');

        $response->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJson([
                'message' => 'Aucune mission trouvée',
            ]);
    }

    // ===== ORDERING TESTS =====

    public function test_missions_are_ordered_by_most_recent_first(): void
    {
        // Create missions with different created_at dates
        $oldMission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'titre' => 'Old Mission',
            'created_at' => now()->subDays(5),
        ]);

        $recentMission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'titre' => 'Recent Mission',
            'created_at' => now()->subDay(),
        ]);

        $newestMission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'titre' => 'Newest Mission',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/missions');

        $response->assertOk()
            ->assertJsonCount(3, 'data');

        $data = $response->json('data');

        // Verify order: newest first
        $this->assertEquals('Newest Mission', $data[0]['titre']);
        $this->assertEquals('Recent Mission', $data[1]['titre']);
        $this->assertEquals('Old Mission', $data[2]['titre']);
    }

    // ===== AUTHORIZATION TESTS =====

    public function test_unauthenticated_user_cannot_list_missions(): void
    {
        $response = $this->getJson('/api/v1/producer/missions');

        $response->assertUnauthorized();
    }

    public function test_face_user_cannot_access_producer_missions_list(): void
    {
        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $response = $this->actingAs($faceUser)
            ->getJson('/api/v1/producer/missions');

        $response->assertForbidden();
    }

    // ===== RESPONSE FORMAT TESTS =====

    public function test_response_includes_expected_mission_fields(): void
    {
        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'titre' => 'Test Mission',
            'description' => 'Test description',
            'date_tournage' => '2026-02-15',
            'budget' => 150000,
            'lieu' => 'Cotonou',
            'nombre_faces_voulu' => 3,
            'status' => MissionStatus::Published,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/missions');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'titre',
                        'description',
                        'date_tournage',
                        'budget',
                        'status',
                        'status_label',
                        'lieu',
                        'nombre_faces_voulu',
                        'created_at',
                        'producer',
                    ],
                ],
                'message',
            ]);
    }

    public function test_response_includes_producer_relationship(): void
    {
        Mission::factory()->create([
            'producer_id' => $this->producer->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/missions');

        $response->assertOk();

        $data = $response->json('data.0');
        $this->assertArrayHasKey('producer', $data);
        $this->assertEquals($this->producer->id, $data['producer']['id']);
    }

    // ===== STATUS FILTERING TESTS =====

    public function test_list_includes_missions_of_all_statuses(): void
    {
        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Draft,
        ]);

        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
        ]);

        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Closed,
        ]);

        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Completed,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/missions');

        $response->assertOk()
            ->assertJsonCount(4, 'data');

        // Verify all statuses are present
        $statuses = collect($response->json('data'))->pluck('status')->toArray();
        $this->assertContains('draft', $statuses);
        $this->assertContains('published', $statuses);
        $this->assertContains('closed', $statuses);
        $this->assertContains('completed', $statuses);
    }
}
