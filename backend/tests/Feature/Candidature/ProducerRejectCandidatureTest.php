<?php

declare(strict_types=1);

namespace Tests\Feature\Candidature;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProducerRejectCandidatureTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    private User $faceUser;

    private Face $face;

    private Mission $mission;

    private Candidature $candidature;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a Producer with User
        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);

        // Create a published mission owned by this Producer
        $this->mission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
        ]);

        // Create a Face with User
        $this->face = Face::factory()->create([
            'nom' => 'Dupont',
            'prenom' => 'Marie',
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);

        // Create a pending candidature
        $this->candidature = Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $this->face->id,
            'status' => CandidatureStatus::Pending,
            'message_motivation' => 'Je suis très motivée pour cette mission.',
        ]);
    }

    public function test_producer_can_reject_pending_candidature(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$this->candidature->id}/reject");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'mission_id',
                    'face_id',
                    'status',
                    'status_label',
                    'message_motivation',
                    'created_at',
                    'updated_at',
                ],
                'message',
            ])
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.status_label', 'Refusée')
            ->assertJsonPath('message', 'Candidature refusée');

        // Verify database was updated
        $this->assertDatabaseHas('candidatures', [
            'id' => $this->candidature->id,
            'status' => 'rejected',
        ]);
    }

    public function test_status_changes_from_pending_to_rejected(): void
    {
        // Verify initial status is pending
        $this->assertEquals(CandidatureStatus::Pending, $this->candidature->status);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$this->candidature->id}/reject");

        $response->assertOk();

        // Refresh and verify status changed
        $this->candidature->refresh();
        $this->assertEquals(CandidatureStatus::Rejected, $this->candidature->status);
    }

    public function test_cannot_reject_already_accepted_candidature(): void
    {
        // Set candidature to accepted
        $this->candidature->status = CandidatureStatus::Accepted;
        $this->candidature->save();

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$this->candidature->id}/reject");

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_STATUS')
            ->assertJsonPath('error.message', 'Seules les candidatures en attente peuvent être refusées');
    }

    public function test_cannot_reject_already_rejected_candidature(): void
    {
        $this->candidature->status = CandidatureStatus::Rejected;
        $this->candidature->save();

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$this->candidature->id}/reject");

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_cannot_reject_confirmed_candidature(): void
    {
        $this->candidature->status = CandidatureStatus::Confirmed;
        $this->candidature->save();

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$this->candidature->id}/reject");

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_cannot_reject_in_progress_candidature(): void
    {
        $this->candidature->status = CandidatureStatus::InProgress;
        $this->candidature->save();

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$this->candidature->id}/reject");

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_cannot_reject_completed_candidature(): void
    {
        $this->candidature->status = CandidatureStatus::Completed;
        $this->candidature->save();

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$this->candidature->id}/reject");

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_face_cannot_reject_candidature(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/producer/candidatures/{$this->candidature->id}/reject");

        $response->assertForbidden()
            ->assertJson([
                'message' => 'Accès réservé aux Producteurs',
            ]);
    }

    public function test_producer_cannot_reject_candidature_for_another_producer_mission(): void
    {
        // Create another producer
        $otherProducer = Producer::factory()->create();
        $otherProducerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $otherProducer->id,
        ]);

        $response = $this->actingAs($otherProducerUser)
            ->postJson("/api/v1/producer/candidatures/{$this->candidature->id}/reject");

        $response->assertForbidden()
            ->assertJson([
                'message' => 'Cette candidature ne concerne pas une de vos missions',
            ]);
    }

    public function test_returns_404_for_non_existent_candidature(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/candidatures/99999/reject');

        $response->assertNotFound();
    }

    public function test_returns_401_when_unauthenticated(): void
    {
        $response = $this->postJson("/api/v1/producer/candidatures/{$this->candidature->id}/reject");

        $response->assertUnauthorized();
    }

    public function test_response_includes_all_candidature_fields(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$this->candidature->id}/reject");

        $response->assertOk()
            ->assertJsonPath('data.id', $this->candidature->id)
            ->assertJsonPath('data.mission_id', $this->mission->id)
            ->assertJsonPath('data.face_id', $this->face->id)
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.status_label', 'Refusée')
            ->assertJsonPath('data.message_motivation', 'Je suis très motivée pour cette mission.');
    }

    public function test_updated_at_is_changed_after_rejection(): void
    {
        $originalUpdatedAt = $this->candidature->updated_at;

        // Wait a moment to ensure different timestamp
        sleep(1);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$this->candidature->id}/reject");

        $response->assertOk();

        $this->candidature->refresh();
        $this->assertNotEquals($originalUpdatedAt, $this->candidature->updated_at);
    }
}
