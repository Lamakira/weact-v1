<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\MissionStatus;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteMissionTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    private Mission $mission;

    protected function setUp(): void
    {
        parent::setUp();

        // Create producer with user
        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);

        // Create a test mission
        $this->mission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'titre' => 'Mission to Delete',
            'status' => MissionStatus::Published,
        ]);
    }

    // ===== SUCCESSFUL DELETION TESTS =====

    public function test_producer_can_delete_own_published_mission(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->deleteJson("/api/v1/producer/missions/{$this->mission->uuid}");

        $response->assertOk()
            ->assertJson([
                'message' => 'Mission supprimée avec succès',
            ]);

        // Verify mission is deleted from database
        $this->assertDatabaseMissing('missions', [
            'id' => $this->mission->id,
        ]);
    }

    public function test_producer_can_delete_own_draft_mission(): void
    {
        $draftMission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Draft,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->deleteJson("/api/v1/producer/missions/{$draftMission->uuid}");

        $response->assertOk()
            ->assertJson([
                'message' => 'Mission supprimée avec succès',
            ]);

        $this->assertDatabaseMissing('missions', [
            'id' => $draftMission->id,
        ]);
    }

    // ===== AUTHORIZATION TESTS =====

    public function test_unauthenticated_user_cannot_delete_mission(): void
    {
        $response = $this->deleteJson("/api/v1/producer/missions/{$this->mission->uuid}");

        $response->assertUnauthorized();

        // Mission should still exist
        $this->assertDatabaseHas('missions', [
            'id' => $this->mission->id,
        ]);
    }

    public function test_other_producer_cannot_delete_mission(): void
    {
        $otherProducer = Producer::factory()->create();
        $otherUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $otherProducer->id,
        ]);

        $response = $this->actingAs($otherUser)
            ->deleteJson("/api/v1/producer/missions/{$this->mission->uuid}");

        $response->assertForbidden();

        // Mission should still exist
        $this->assertDatabaseHas('missions', [
            'id' => $this->mission->id,
        ]);
    }

    public function test_face_cannot_delete_mission(): void
    {
        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $response = $this->actingAs($faceUser)
            ->deleteJson("/api/v1/producer/missions/{$this->mission->uuid}");

        $response->assertForbidden();

        // Mission should still exist
        $this->assertDatabaseHas('missions', [
            'id' => $this->mission->id,
        ]);
    }

    // ===== STATUS RESTRICTION TESTS =====

    public function test_cannot_delete_closed_mission(): void
    {
        $closedMission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Closed,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->deleteJson("/api/v1/producer/missions/{$closedMission->uuid}");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['mission'])
            ->assertJson([
                'errors' => [
                    'mission' => ['Une mission clôturée, en attente de paiement ou terminée ne peut pas être supprimée'],
                ],
            ]);

        $this->assertDatabaseHas('missions', [
            'id' => $closedMission->id,
        ]);
    }

    public function test_cannot_delete_completed_mission(): void
    {
        $completedMission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Completed,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->deleteJson("/api/v1/producer/missions/{$completedMission->uuid}");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['mission'])
            ->assertJson([
                'errors' => [
                    'mission' => ['Une mission clôturée, en attente de paiement ou terminée ne peut pas être supprimée'],
                ],
            ]);

        $this->assertDatabaseHas('missions', [
            'id' => $completedMission->id,
        ]);
    }

    public function test_cannot_delete_pending_payment_mission(): void
    {
        $pendingMission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::PendingPayment,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->deleteJson("/api/v1/producer/missions/{$pendingMission->uuid}");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['mission'])
            ->assertJson([
                'errors' => [
                    'mission' => ['Une mission clôturée, en attente de paiement ou terminée ne peut pas être supprimée'],
                ],
            ]);

        $this->assertDatabaseHas('missions', [
            'id' => $pendingMission->id,
        ]);
    }

    public function test_deleting_mission_with_active_candidatures_cancels_them(): void
    {
        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $candidature = Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $face->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->producerUser)
            ->deleteJson("/api/v1/producer/missions/{$this->mission->uuid}");

        $response->assertOk();

        // Mission is deleted
        $this->assertDatabaseMissing('missions', ['id' => $this->mission->id]);

        // Candidature is cascade-deleted with the mission
        $this->assertDatabaseMissing('candidatures', ['id' => $candidature->id]);

        // Face is notified before deletion
        $this->assertDatabaseHas('notifications', [
            'user_id' => $faceUser->id,
            'type' => 'mission_deleted_candidature_cancelled',
        ]);
    }

    // ===== RESPONSE FORMAT TESTS =====

    public function test_delete_response_has_correct_format(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->deleteJson("/api/v1/producer/missions/{$this->mission->uuid}");

        $response->assertOk()
            ->assertJsonStructure([
                'message',
            ])
            ->assertJson([
                'message' => 'Mission supprimée avec succès',
            ]);
    }

    // ===== NON-EXISTENT MISSION TEST =====

    public function test_cannot_delete_non_existent_mission(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->deleteJson('/api/v1/producer/missions/00000000-0000-0000-0000-000000000000');

        $response->assertNotFound();
    }
}
