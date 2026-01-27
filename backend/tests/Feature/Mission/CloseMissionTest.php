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

class CloseMissionTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    private Mission $mission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
        $this->mission = Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
        ]);
    }

    /**
     * AC#1, AC#10: Producer can close a published mission successfully.
     */
    public function test_producer_can_close_published_mission(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->id}/close");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $this->mission->id,
                    'status' => 'closed',
                ],
                'message' => 'Mission clôturée avec succès',
            ]);

        // Verify database was updated
        $this->assertDatabaseHas('missions', [
            'id' => $this->mission->id,
            'status' => 'closed',
        ]);
    }

    /**
     * AC#3: Closed mission has correct status label.
     */
    public function test_closed_mission_has_correct_status_label(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->id}/close");

        $response->assertOk()
            ->assertJsonPath('data.status_label', 'Clôturée');
    }

    /**
     * AC#4: Cannot close a draft mission.
     */
    public function test_cannot_close_draft_mission(): void
    {
        $draftMission = Mission::factory()->draft()->create([
            'producer_id' => $this->producer->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$draftMission->id}/close");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJsonPath('errors.status.0', 'Seules les missions publiées peuvent être clôturées');

        // Verify database was NOT updated
        $this->assertDatabaseHas('missions', [
            'id' => $draftMission->id,
            'status' => 'draft',
        ]);
    }

    /**
     * AC#5: Cannot close an already closed mission.
     */
    public function test_cannot_close_already_closed_mission(): void
    {
        $closedMission = Mission::factory()->closed()->create([
            'producer_id' => $this->producer->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$closedMission->id}/close");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJsonPath('errors.status.0', 'Cette mission est déjà clôturée');
    }

    /**
     * AC#6: Cannot close a completed mission.
     */
    public function test_cannot_close_completed_mission(): void
    {
        $completedMission = Mission::factory()->completed()->create([
            'producer_id' => $this->producer->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$completedMission->id}/close");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJsonPath('errors.status.0', 'Cette mission est déjà terminée');
    }

    /**
     * AC#7: Other producer cannot close another producer's mission.
     */
    public function test_other_producer_cannot_close_mission(): void
    {
        $otherProducer = Producer::factory()->create();
        $otherUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $otherProducer->id,
        ]);

        $response = $this->actingAs($otherUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->id}/close");

        $response->assertForbidden();

        // Verify database was NOT updated
        $this->assertDatabaseHas('missions', [
            'id' => $this->mission->id,
            'status' => 'published',
        ]);
    }

    /**
     * AC#8: Unauthenticated user cannot close a mission.
     */
    public function test_unauthenticated_user_cannot_close_mission(): void
    {
        $response = $this->postJson("/api/v1/producer/missions/{$this->mission->id}/close");

        $response->assertUnauthorized();
    }

    /**
     * AC#9: Face user cannot close a mission.
     */
    public function test_face_user_cannot_close_mission(): void
    {
        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $response = $this->actingAs($faceUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->id}/close");

        $response->assertForbidden();
    }

    /**
     * AC#10: Response includes complete mission data.
     */
    public function test_close_response_includes_complete_mission_data(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->id}/close");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'titre',
                    'description',
                    'date_tournage',
                    'budget',
                    'status',
                    'status_label',
                    'lieu',
                    'nombre_faces_voulu',
                    'is_accepting_candidatures',
                    'created_at',
                    'producer',
                ],
                'message',
            ])
            ->assertJsonPath('data.is_accepting_candidatures', false);
    }

    /**
     * Test that closing a mission updates is_accepting_candidatures to false.
     */
    public function test_closed_mission_is_not_accepting_candidatures(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->mission->id}/close");

        $response->assertOk()
            ->assertJsonPath('data.is_accepting_candidatures', false);
    }

    /**
     * Test that non-existent mission returns 404.
     */
    public function test_close_nonexistent_mission_returns_404(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions/999999/close');

        $response->assertNotFound();
    }
}
