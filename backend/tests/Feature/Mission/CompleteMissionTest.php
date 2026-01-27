<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteMissionTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    private Mission $publishedMission;

    private Mission $closedMission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
        $this->publishedMission = Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
        ]);
        $this->closedMission = Mission::factory()->closed()->create([
            'producer_id' => $this->producer->id,
        ]);
    }

    /**
     * AC#1, AC#10: Producer can complete a published mission successfully.
     */
    public function test_producer_can_complete_published_mission(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->publishedMission->id}/complete");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $this->publishedMission->id,
                    'status' => 'completed',
                ],
                'message' => 'Mission marquée comme terminée',
            ]);

        // Verify database was updated
        $this->assertDatabaseHas('missions', [
            'id' => $this->publishedMission->id,
            'status' => 'completed',
        ]);
    }

    /**
     * AC#1: Producer can complete a closed mission successfully.
     */
    public function test_producer_can_complete_closed_mission(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->closedMission->id}/complete");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $this->closedMission->id,
                    'status' => 'completed',
                ],
                'message' => 'Mission marquée comme terminée',
            ]);

        // Verify database was updated
        $this->assertDatabaseHas('missions', [
            'id' => $this->closedMission->id,
            'status' => 'completed',
        ]);
    }

    /**
     * AC#2: Completed mission has correct status label "Terminée".
     */
    public function test_completed_mission_has_correct_status_label(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->closedMission->id}/complete");

        $response->assertOk()
            ->assertJsonPath('data.status_label', 'Terminée');
    }

    /**
     * AC#4: Cannot complete a draft mission.
     */
    public function test_cannot_complete_draft_mission(): void
    {
        $draftMission = Mission::factory()->draft()->create([
            'producer_id' => $this->producer->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$draftMission->id}/complete");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJsonPath('errors.status.0', 'Seules les missions publiées ou clôturées peuvent être marquées comme terminées');

        // Verify database was NOT updated
        $this->assertDatabaseHas('missions', [
            'id' => $draftMission->id,
            'status' => 'draft',
        ]);
    }

    /**
     * AC#6: Cannot complete an already completed mission.
     */
    public function test_cannot_complete_already_completed_mission(): void
    {
        $completedMission = Mission::factory()->completed()->create([
            'producer_id' => $this->producer->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$completedMission->id}/complete");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJsonPath('errors.status.0', 'Cette mission est déjà terminée');
    }

    /**
     * AC#7: Other producer cannot complete another producer's mission.
     */
    public function test_other_producer_cannot_complete_mission(): void
    {
        $otherProducer = Producer::factory()->create();
        $otherUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $otherProducer->id,
        ]);

        $response = $this->actingAs($otherUser)
            ->postJson("/api/v1/producer/missions/{$this->closedMission->id}/complete");

        $response->assertForbidden();

        // Verify database was NOT updated
        $this->assertDatabaseHas('missions', [
            'id' => $this->closedMission->id,
            'status' => 'closed',
        ]);
    }

    /**
     * AC#8: Unauthenticated user cannot complete a mission.
     */
    public function test_unauthenticated_user_cannot_complete_mission(): void
    {
        $response = $this->postJson("/api/v1/producer/missions/{$this->closedMission->id}/complete");

        $response->assertUnauthorized();
    }

    /**
     * AC#9: Face user cannot complete a mission.
     */
    public function test_face_user_cannot_complete_mission(): void
    {
        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $response = $this->actingAs($faceUser)
            ->postJson("/api/v1/producer/missions/{$this->closedMission->id}/complete");

        $response->assertForbidden();
    }

    /**
     * AC#10: Response includes complete mission data.
     */
    public function test_complete_response_includes_complete_mission_data(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->closedMission->id}/complete");

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
            ]);
    }

    /**
     * Test that completed mission is not accepting candidatures.
     */
    public function test_completed_mission_is_not_accepting_candidatures(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->closedMission->id}/complete");

        $response->assertOk()
            ->assertJsonPath('data.is_accepting_candidatures', false);
    }

    /**
     * Test that non-existent mission returns 404.
     */
    public function test_complete_nonexistent_mission_returns_404(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions/999999/complete');

        $response->assertNotFound();
    }
}
