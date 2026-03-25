<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteMissionTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    private User $faceUser;

    private Face $face;

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

        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);

        $this->publishedMission = Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
        ]);

        $this->closedMission = Mission::factory()->closed()->create([
            'producer_id' => $this->producer->id,
        ]);
    }

    private function createPaidSelection(Mission $mission, CandidatureStatus $status = CandidatureStatus::Confirmed): Candidature
    {
        $candidature = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $this->face->id,
            'status' => $status,
        ]);

        $payment = MissionPayment::create([
            'mission_id' => $mission->id,
            'producer_id' => $this->producer->id,
            'nombre_faces_retenues' => 1,
            'budget_par_face' => 100000,
            'montant_sous_total' => 100000,
            'commission_producteur' => 10000,
            'montant_total_producteur' => 110000,
            'commission_faces_total' => 10000,
            'montant_total_faces' => 90000,
            'status' => MissionPaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        MissionPaymentCandidature::create([
            'mission_payment_id' => $payment->id,
            'candidature_id' => $candidature->id,
            'face_id' => $this->face->id,
            'montant_face_recoit' => 90000,
            'escrow_status' => EscrowStatus::Locked,
            'locked_at' => now(),
        ]);

        return $candidature;
    }

    public function test_producer_can_complete_closed_mission_with_paid_confirmed_selection(): void
    {
        $candidature = $this->createPaidSelection($this->closedMission, CandidatureStatus::Confirmed);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->closedMission->id}/complete");

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('message', 'Mission marquée comme terminée');

        $this->assertDatabaseHas('missions', [
            'id' => $this->closedMission->id,
            'status' => 'completed',
        ]);

        $this->faceUser->refresh();
        $this->assertSame(90000, $this->faceUser->balance);

        $this->assertDatabaseHas('candidatures', [
            'id' => $candidature->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->faceUser->id,
            'type' => 'mission_completed',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->producerUser->id,
            'type' => 'mission_completed_producer',
        ]);
    }

    public function test_cannot_complete_published_mission(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->publishedMission->id}/complete");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJsonPath('errors.status.0', 'Seules les missions clôturées peuvent être marquées comme terminées');
    }

    public function test_cannot_complete_closed_mission_without_paid_payment(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->closedMission->id}/complete");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJsonPath('errors.status.0', 'Le paiement doit être confirmé avant de terminer la mission');
    }

    public function test_cannot_complete_when_selected_face_has_not_confirmed_participation(): void
    {
        $this->createPaidSelection($this->closedMission, CandidatureStatus::Accepted);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->closedMission->id}/complete");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['candidatures'])
            ->assertJsonPath('errors.candidatures.0', 'Toutes les faces sélectionnées doivent confirmer leur participation avant de terminer la mission');
    }

    public function test_cannot_complete_draft_mission(): void
    {
        $draftMission = Mission::factory()->draft()->create([
            'producer_id' => $this->producer->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$draftMission->id}/complete");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJsonPath('errors.status.0', 'Seules les missions clôturées peuvent être marquées comme terminées');
    }

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
    }

    public function test_unauthenticated_user_cannot_complete_mission(): void
    {
        $response = $this->postJson("/api/v1/producer/missions/{$this->closedMission->id}/complete");

        $response->assertUnauthorized();
    }

    public function test_complete_nonexistent_mission_returns_404(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions/999999/complete');

        $response->assertNotFound();
    }
}
