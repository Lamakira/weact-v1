<?php

declare(strict_types=1);

namespace Tests\Feature\Candidature;

use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
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

    private MissionPayment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->face = Face::factory()->create([
            'nom' => 'Dupont',
            'prenom' => 'Marie',
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);

        $this->mission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Closed,
        ]);

        $this->candidature = Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $this->face->id,
            'status' => CandidatureStatus::Accepted,
            'message_motivation' => 'Je suis très motivée pour cette mission.',
        ]);

        $this->payment = MissionPayment::create([
            'mission_id' => $this->mission->id,
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
            'mission_payment_id' => $this->payment->id,
            'candidature_id' => $this->candidature->id,
            'face_id' => $this->face->id,
            'montant_face_recoit' => 90000,
            'escrow_status' => EscrowStatus::Locked,
            'locked_at' => now(),
        ]);
    }

    public function test_face_can_confirm_accepted_candidature_after_payment_confirmation(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->candidature->uuid}/confirm");

        $response->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('message', 'Participation confirmée');

        // With a single selected face, confirming immediately transitions to in_progress
        $this->assertDatabaseHas('candidatures', [
            'id' => $this->candidature->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_cannot_confirm_candidature_when_payment_is_not_confirmed(): void
    {
        $this->payment->update([
            'status' => MissionPaymentStatus::Pending,
            'paid_at' => null,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->candidature->uuid}/confirm");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'PAYMENT_NOT_CONFIRMED');
    }

    public function test_cannot_confirm_candidature_not_in_final_selection(): void
    {
        // Use a different face to avoid unique(face_id, mission_id) constraint
        $otherFace = Face::factory()->create();
        $otherFaceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $otherFace->id,
        ]);
        $otherCandidature = Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $otherFace->id,
            'status' => CandidatureStatus::Accepted,
        ]);

        $response = $this->actingAs($otherFaceUser)
            ->postJson("/api/v1/face/candidatures/{$otherCandidature->uuid}/confirm");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'NOT_IN_FINAL_SELECTION');
    }

    public function test_cannot_confirm_pending_candidature(): void
    {
        $this->candidature->update(['status' => CandidatureStatus::Pending]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->candidature->uuid}/confirm");

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_cannot_confirm_already_confirmed_candidature(): void
    {
        $this->candidature->update(['status' => CandidatureStatus::Confirmed]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->candidature->uuid}/confirm");

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    public function test_producer_cannot_confirm_candidature(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/face/candidatures/{$this->candidature->uuid}/confirm");

        $response->assertForbidden();
    }

    public function test_face_cannot_confirm_another_face_candidature(): void
    {
        $otherFace = Face::factory()->create();
        $otherFaceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $otherFace->id,
        ]);

        $response = $this->actingAs($otherFaceUser)
            ->postJson("/api/v1/face/candidatures/{$this->candidature->uuid}/confirm");

        $response->assertForbidden()
            ->assertJson([
                'message' => 'Cette candidature ne vous appartient pas',
            ]);
    }
}
