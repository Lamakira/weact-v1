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

class FaceConfirmCandidatureTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private Face $face;

    private User $producerUser;

    private Producer $producer;

    private Mission $mission;

    private Candidature $candidature;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a Face with User
        $this->face = Face::factory()->create([
            'nom' => 'Dupont',
            'prenom' => 'Marie',
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);

        // Create a Producer with User
        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);

        // Create a published mission owned by the Producer
        $this->mission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
        ]);

        // Create an accepted candidature (Face can confirm after Producer accepts)
        $this->candidature = Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $this->face->id,
            'status' => CandidatureStatus::Accepted,
            'message_motivation' => 'Je suis très motivée pour cette mission.',
        ]);
    }

    public function test_face_can_confirm_accepted_candidature(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->candidature->id}/confirm");

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
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.status_label', 'Confirmée')
            ->assertJsonPath('message', 'Participation confirmée');

        // Verify database was updated
        $this->assertDatabaseHas('candidatures', [
            'id' => $this->candidature->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_status_changes_from_accepted_to_confirmed(): void
    {
        // Verify initial status is accepted
        $this->assertEquals(CandidatureStatus::Accepted, $this->candidature->status);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->candidature->id}/confirm");

        $response->assertOk();

        // Refresh and verify status changed
        $this->candidature->refresh();
        $this->assertEquals(CandidatureStatus::Confirmed, $this->candidature->status);
    }

    public function test_cannot_confirm_pending_candidature(): void
    {
        // Set candidature to pending
        $this->candidature->status = CandidatureStatus::Pending;
        $this->candidature->save();

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->candidature->id}/confirm");

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_STATUS')
            ->assertJsonPath('error.message', 'Seules les candidatures acceptées peuvent être confirmées');
    }

    public function test_cannot_confirm_already_confirmed_candidature(): void
    {
        $this->candidature->status = CandidatureStatus::Confirmed;
        $this->candidature->save();

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->candidature->id}/confirm");

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_cannot_confirm_rejected_candidature(): void
    {
        $this->candidature->status = CandidatureStatus::Rejected;
        $this->candidature->save();

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->candidature->id}/confirm");

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_cannot_confirm_in_progress_candidature(): void
    {
        $this->candidature->status = CandidatureStatus::InProgress;
        $this->candidature->save();

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->candidature->id}/confirm");

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_cannot_confirm_completed_candidature(): void
    {
        $this->candidature->status = CandidatureStatus::Completed;
        $this->candidature->save();

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->candidature->id}/confirm");

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_producer_cannot_confirm_candidature(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/face/candidatures/{$this->candidature->id}/confirm");

        // The face middleware blocks Producers with a generic 403 message
        $response->assertForbidden();
    }

    public function test_face_cannot_confirm_another_face_candidature(): void
    {
        // Create another face
        $otherFace = Face::factory()->create();
        $otherFaceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $otherFace->id,
        ]);

        $response = $this->actingAs($otherFaceUser)
            ->postJson("/api/v1/face/candidatures/{$this->candidature->id}/confirm");

        $response->assertForbidden()
            ->assertJson([
                'message' => 'Cette candidature ne vous appartient pas',
            ]);
    }

    public function test_returns_404_for_non_existent_candidature(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/candidatures/99999/confirm');

        $response->assertNotFound();
    }

    public function test_returns_401_when_unauthenticated(): void
    {
        $response = $this->postJson("/api/v1/face/candidatures/{$this->candidature->id}/confirm");

        $response->assertUnauthorized();
    }

    public function test_response_includes_all_candidature_fields(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->candidature->id}/confirm");

        $response->assertOk()
            ->assertJsonPath('data.id', $this->candidature->id)
            ->assertJsonPath('data.mission_id', $this->mission->id)
            ->assertJsonPath('data.face_id', $this->face->id)
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.status_label', 'Confirmée')
            ->assertJsonPath('data.message_motivation', 'Je suis très motivée pour cette mission.');
    }

    public function test_updated_at_is_changed_after_confirmation(): void
    {
        $originalUpdatedAt = $this->candidature->updated_at;

        // Wait a moment to ensure different timestamp
        sleep(1);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->candidature->id}/confirm");

        $response->assertOk();

        $this->candidature->refresh();
        $this->assertNotEquals($originalUpdatedAt, $this->candidature->updated_at);
    }
}
