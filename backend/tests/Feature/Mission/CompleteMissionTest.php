<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\AttendanceStatus;
use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\FinancialEventType;
use App\Enums\MissionPaymentStatus;
use App\Mail\MissionCompletedMail;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\FinancialEvent;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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
        Mail::fake();

        $candidature = $this->createPaidSelection($this->closedMission, CandidatureStatus::Confirmed);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->closedMission->uuid}/complete");

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

        // FIX-26.2 — bridge auto-marks pending entry as present, then releaseFunds → Released.
        $this->assertDatabaseHas('mission_payment_candidatures', [
            'candidature_id' => $candidature->id,
            'attendance_status' => 'present',
            'escrow_status' => 'released',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->faceUser->id,
            'type' => 'mission_completed',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->producerUser->id,
            'type' => 'mission_completed_producer',
        ]);

        // FIX-24.4: MissionCompletedMail queued to the Face user with full payload.
        Mail::assertQueuedCount(1);
        Mail::assertQueued(
            MissionCompletedMail::class,
            fn (MissionCompletedMail $mail): bool => $mail->hasTo($this->faceUser->email)
                && $mail->face->is($this->face)
                && $mail->mission->is($this->closedMission)
                && $mail->amount === 90000,
        );
    }

    public function test_complete_mission_with_three_confirmed_faces_queues_three_mails(): void
    {
        Mail::fake();

        $mission = Mission::factory()->closed()->create([
            'producer_id' => $this->producer->id,
        ]);

        // 3 distinct Face/User/Candidature (we don't reuse $this->face/$this->faceUser
        // to keep this test self-contained from setUp side-effects).
        // Status MUST be Confirmed — Accepted would trip hasUnconfirmedSelectedFaces
        // and short-circuit before releaseFunds (covered by another test).
        $faces = [];
        $users = [];
        $candidatures = [];
        for ($i = 0; $i < 3; $i++) {
            $face = Face::factory()->create([
                'prenom' => "Face{$i}",
                'nom' => 'Test',
            ]);
            $user = User::factory()->create([
                'userable_type' => Face::class,
                'userable_id' => $face->id,
                'email' => "face{$i}@example.test",
            ]);
            $candidature = Candidature::factory()->create([
                'mission_id' => $mission->id,
                'face_id' => $face->id,
                'status' => CandidatureStatus::Confirmed,
            ]);
            $faces[] = $face;
            $users[] = $user;
            $candidatures[] = $candidature;
        }

        // Single MissionPayment shared across the 3 entries — invariants scaled × 3.
        $payment = MissionPayment::create([
            'mission_id' => $mission->id,
            'producer_id' => $this->producer->id,
            'nombre_faces_retenues' => 3,
            'budget_par_face' => 100000,
            'montant_sous_total' => 300000,
            'commission_producteur' => 30000,
            'montant_total_producteur' => 330000,
            'commission_faces_total' => 30000,
            'montant_total_faces' => 270000,
            'status' => MissionPaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        foreach ($candidatures as $i => $candidature) {
            MissionPaymentCandidature::create([
                'mission_payment_id' => $payment->id,
                'candidature_id' => $candidature->id,
                'face_id' => $faces[$i]->id,
                'montant_face_recoit' => 90000,
                'escrow_status' => EscrowStatus::Locked,
                'locked_at' => now(),
            ]);
        }

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/complete");

        $response->assertOk();

        // Each Face balance credited and each Face received a MissionCompletedMail.
        Mail::assertQueuedCount(3);
        foreach ($users as $i => $user) {
            $user->refresh();
            $this->assertSame(90000, $user->balance, "User #{$i} should have balance 90000");

            Mail::assertQueued(
                MissionCompletedMail::class,
                fn (MissionCompletedMail $mail): bool => $mail->hasTo($user->email)
                    && $mail->face->is($faces[$i])
                    && $mail->mission->is($mission)
                    && $mail->amount === 90000,
            );
        }

        // FIX-26.2 — bridge auto-marks all 3 pending entries as present, all released.
        $this->assertSame(
            3,
            MissionPaymentCandidature::where('mission_payment_id', $payment->id)
                ->where('attendance_status', 'present')
                ->where('escrow_status', 'released')
                ->count(),
        );
    }

    public function test_complete_mission_with_one_present_one_absent_one_disputed_routes_correctly(): void
    {
        Mail::fake();

        $mission = Mission::factory()->closed()->create([
            'producer_id' => $this->producer->id,
        ]);

        $faces = [];
        $users = [];
        $candidatures = [];
        for ($i = 0; $i < 3; $i++) {
            $face = Face::factory()->create([
                'prenom' => "Mixed{$i}",
                'nom' => 'Test',
            ]);
            $user = User::factory()->create([
                'userable_type' => Face::class,
                'userable_id' => $face->id,
                'email' => "mixed{$i}@example.test",
            ]);
            $candidature = Candidature::factory()->create([
                'mission_id' => $mission->id,
                'face_id' => $face->id,
                'status' => CandidatureStatus::Confirmed,
            ]);
            $faces[] = $face;
            $users[] = $user;
            $candidatures[] = $candidature;
        }

        $payment = MissionPayment::create([
            'mission_id' => $mission->id,
            'producer_id' => $this->producer->id,
            'nombre_faces_retenues' => 3,
            'budget_par_face' => 100000,
            'montant_sous_total' => 300000,
            'commission_producteur' => 30000,
            'montant_total_producteur' => 330000,
            'commission_faces_total' => 30000,
            'montant_total_faces' => 270000,
            'status' => MissionPaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $explicitStatuses = [AttendanceStatus::Present, AttendanceStatus::Absent, AttendanceStatus::Disputed];
        $entries = [];
        foreach ($candidatures as $i => $candidature) {
            $entries[] = MissionPaymentCandidature::create([
                'mission_payment_id' => $payment->id,
                'candidature_id' => $candidature->id,
                'face_id' => $faces[$i]->id,
                'montant_face_recoit' => 90000,
                'escrow_status' => EscrowStatus::Locked,
                'locked_at' => now(),
                'attendance_status' => $explicitStatuses[$i],
            ]);
        }

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/complete");

        $response->assertOk()->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('missions', [
            'id' => $mission->id,
            'status' => 'completed',
        ]);

        // Face[present] received the release.
        $users[0]->refresh();
        $this->assertSame(90000, $users[0]->balance);

        // Face[absent] received nothing.
        $users[1]->refresh();
        $this->assertSame(0, $users[1]->balance);

        // Face[disputed] received nothing.
        $users[2]->refresh();
        $this->assertSame(0, $users[2]->balance);

        // Producer received exactly one refund (Face[absent]).
        $this->producerUser->refresh();
        $this->assertSame(90000, $this->producerUser->balance);

        // Disputed entry stays Locked + disputed (invariant 5: Completed mission may keep
        // a disputed Locked entry pending admin resolution in FIX-26.8).
        $this->assertDatabaseHas('mission_payment_candidatures', [
            'id' => $entries[2]->id,
            'escrow_status' => 'locked',
            'attendance_status' => 'disputed',
        ]);

        $this->assertSame(
            1,
            FinancialEvent::where('type', FinancialEventType::EscrowRelease)->count(),
        );
        $this->assertSame(
            1,
            FinancialEvent::where('type', FinancialEventType::Refund)->count(),
        );
        $this->assertDatabaseMissing('financial_events', [
            'idempotency_key' => "mission_attendance_escrow_release:{$entries[2]->id}",
        ]);
        $this->assertDatabaseMissing('financial_events', [
            'idempotency_key' => "mission_attendance_refund:{$entries[2]->id}",
        ]);

        // Only 1 mail queued (Face[present]).
        Mail::assertQueuedCount(1);
    }

    public function test_cannot_complete_published_mission(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->publishedMission->uuid}/complete");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJsonPath('errors.status.0', 'Seules les missions clôturées peuvent être marquées comme terminées');
    }

    public function test_cannot_complete_closed_mission_without_paid_payment(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->closedMission->uuid}/complete");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJsonPath('errors.status.0', 'Le paiement doit être confirmé avant de terminer la mission');

        Mail::assertNothingQueued();
    }

    public function test_cannot_complete_when_selected_face_has_not_confirmed_participation(): void
    {
        Mail::fake();

        $this->createPaidSelection($this->closedMission, CandidatureStatus::Accepted);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$this->closedMission->uuid}/complete");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['candidatures'])
            ->assertJsonPath('errors.candidatures.0', 'Toutes les faces sélectionnées doivent confirmer leur participation avant de terminer la mission');

        Mail::assertNothingQueued();
    }

    public function test_cannot_complete_draft_mission(): void
    {
        $draftMission = Mission::factory()->draft()->create([
            'producer_id' => $this->producer->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$draftMission->uuid}/complete");

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
            ->postJson("/api/v1/producer/missions/{$completedMission->uuid}/complete");

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
            ->postJson("/api/v1/producer/missions/{$this->closedMission->uuid}/complete");

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_complete_mission(): void
    {
        $response = $this->postJson("/api/v1/producer/missions/{$this->closedMission->uuid}/complete");

        $response->assertUnauthorized();
    }

    public function test_complete_nonexistent_mission_returns_404(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions/00000000-0000-0000-0000-000000000000/complete');

        $response->assertNotFound();
    }
}
