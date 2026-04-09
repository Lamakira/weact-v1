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

class FaceCancelCandidatureTest extends TestCase
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

        // Create a pending candidature (Face can cancel pending ones)
        $this->candidature = Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $this->face->id,
            'status' => CandidatureStatus::Pending,
            'message_motivation' => 'Je suis très motivée pour cette mission.',
        ]);
    }

    public function test_face_can_cancel_pending_candidature(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->candidature->uuid}/cancel");

        $response->assertOk()
            ->assertJsonPath('message', 'Candidature annulée avec succès.');

        // Verify database was updated
        $this->assertDatabaseHas('candidatures', [
            'id' => $this->candidature->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_status_changes_from_pending_to_cancelled(): void
    {
        $this->assertEquals(CandidatureStatus::Pending, $this->candidature->status);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->candidature->uuid}/cancel");

        $response->assertOk();

        $this->candidature->refresh();
        $this->assertEquals(CandidatureStatus::Cancelled, $this->candidature->status);
    }

    public function test_cannot_cancel_accepted_candidature(): void
    {
        $this->candidature->status = CandidatureStatus::Accepted;
        $this->candidature->save();

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->candidature->uuid}/cancel");

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Seules les candidatures en attente peuvent être annulées.');
    }

    public function test_cannot_cancel_confirmed_candidature(): void
    {
        $this->candidature->status = CandidatureStatus::Confirmed;
        $this->candidature->save();

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->candidature->uuid}/cancel");

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Seules les candidatures en attente peuvent être annulées.');
    }

    public function test_cannot_cancel_rejected_candidature(): void
    {
        $this->candidature->status = CandidatureStatus::Rejected;
        $this->candidature->save();

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->candidature->uuid}/cancel");

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Seules les candidatures en attente peuvent être annulées.');
    }

    public function test_cannot_cancel_in_progress_candidature(): void
    {
        $this->candidature->status = CandidatureStatus::InProgress;
        $this->candidature->save();

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->candidature->uuid}/cancel");

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Seules les candidatures en attente peuvent être annulées.');
    }

    public function test_cannot_cancel_completed_candidature(): void
    {
        $this->candidature->status = CandidatureStatus::Completed;
        $this->candidature->save();

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->candidature->uuid}/cancel");

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Seules les candidatures en attente peuvent être annulées.');
    }

    public function test_face_cannot_cancel_another_face_candidature(): void
    {
        $otherFace = Face::factory()->create();
        $otherFaceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $otherFace->id,
        ]);

        $response = $this->actingAs($otherFaceUser)
            ->postJson("/api/v1/face/candidatures/{$this->candidature->uuid}/cancel");

        $response->assertForbidden()
            ->assertJson([
                'message' => 'Cette candidature ne vous appartient pas',
            ]);
    }

    public function test_producer_cannot_cancel_candidature(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/face/candidatures/{$this->candidature->uuid}/cancel");

        $response->assertForbidden();
    }

    public function test_guest_cannot_cancel_candidature(): void
    {
        $response = $this->postJson("/api/v1/face/candidatures/{$this->candidature->uuid}/cancel");

        $response->assertUnauthorized();
    }

    public function test_returns_404_for_non_existent_candidature(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/candidatures/00000000-0000-0000-0000-000000000000/cancel');

        $response->assertNotFound();
    }
}
