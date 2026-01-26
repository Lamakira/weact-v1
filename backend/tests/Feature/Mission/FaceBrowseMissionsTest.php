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

class FaceBrowseMissionsTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private Face $face;

    private User $producerUser;

    private Producer $producer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a Face user
        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);

        // Create a Producer user
        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    public function test_face_can_list_published_missions(): void
    {
        // Create published missions
        Mission::factory()->count(3)->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'titre',
                        'description',
                        'date_tournage',
                        'budget',
                        'lieu',
                        'type_mission',
                        'nombre_faces_voulu',
                        'status',
                        'status_label',
                        'producer',
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

    public function test_pagination_returns_12_per_page(): void
    {
        // Create 15 published missions
        Mission::factory()->count(15)->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions');

        $response->assertStatus(200)
            ->assertJsonCount(12, 'data')
            ->assertJsonPath('meta.per_page', 12)
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_missions_ordered_by_newest_first(): void
    {
        // Create missions with different dates
        $oldMission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'titre' => 'Old Mission',
            'created_at' => now()->subDays(5),
        ]);

        $newMission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'titre' => 'New Mission',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals('New Mission', $data[0]['titre']);
        $this->assertEquals('Old Mission', $data[1]['titre']);
    }

    public function test_only_published_missions_are_returned(): void
    {
        // Create missions with different statuses
        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'titre' => 'Published Mission',
        ]);

        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Draft,
            'titre' => 'Draft Mission',
        ]);

        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Closed,
            'titre' => 'Closed Mission',
        ]);

        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Completed,
            'titre' => 'Completed Mission',
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.titre', 'Published Mission');
    }

    public function test_producer_cannot_access_face_missions_endpoint(): void
    {
        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/face/missions');

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Cette action n\'est pas autorisée',
            ]);
    }

    public function test_unauthenticated_user_cannot_access(): void
    {
        $response = $this->getJson('/api/v1/face/missions');

        $response->assertStatus(401);
    }

    public function test_response_includes_producer_data(): void
    {
        $producer = Producer::factory()->create([
            'type' => 'agency',
            'agency_name' => 'Studio XYZ',
        ]);

        Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Published,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.producer.agency_name', 'Studio XYZ')
            ->assertJsonPath('data.0.producer.type', 'agency');
    }

    public function test_empty_list_returns_empty_array_with_message(): void
    {
        // No missions created

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('message', 'Aucune mission disponible pour le moment')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_pagination_second_page(): void
    {
        // Create 15 published missions
        Mission::factory()->count(15)->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions?page=2');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_mission_card_has_required_fields(): void
    {
        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'titre' => 'Test Mission',
            'description' => 'Test Description',
            'date_tournage' => '2026-02-15',
            'budget' => 150000,
            'lieu' => 'Cotonou',
            'type_mission' => 'publicite',
            'nombre_faces_voulu' => 3,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.titre', 'Test Mission')
            ->assertJsonPath('data.0.description', 'Test Description')
            ->assertJsonPath('data.0.budget', 150000)
            ->assertJsonPath('data.0.lieu', 'Cotonou')
            ->assertJsonPath('data.0.type_mission', 'publicite')
            ->assertJsonPath('data.0.nombre_faces_voulu', 3)
            ->assertJsonPath('data.0.status', 'published')
            ->assertJsonPath('data.0.status_label', 'Publiée');

        // Check date_tournage exists and is in ISO 8601 format
        $data = $response->json('data.0');
        $this->assertNotNull($data['date_tournage']);
        $this->assertStringContainsString('2026-02-15', $data['date_tournage']);
    }
}
