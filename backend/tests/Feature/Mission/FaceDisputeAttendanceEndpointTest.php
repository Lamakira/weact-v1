<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\AttendanceStatus;
use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
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

class FaceDisputeAttendanceEndpointTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private User $producerUser;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    /**
     * @return array{0: Mission, 1: list<array{candidature: Candidature, entry: MissionPaymentCandidature, faceUser: User, face: Face}>}
     */
    private function createPaidMissionWithFaces(int $faceCount): array
    {
        $mission = Mission::factory()->closed()->create(['producer_id' => $this->producer->id]);

        $payment = MissionPayment::create([
            'mission_id' => $mission->id,
            'producer_id' => $this->producer->id,
            'nombre_faces_retenues' => $faceCount,
            'budget_par_face' => 100000,
            'montant_sous_total' => 100000 * $faceCount,
            'commission_producteur' => 10000 * $faceCount,
            'montant_total_producteur' => 110000 * $faceCount,
            'commission_faces_total' => 10000 * $faceCount,
            'montant_total_faces' => 90000 * $faceCount,
            'status' => MissionPaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $faces = [];

        for ($i = 0; $i < $faceCount; $i++) {
            $face = Face::factory()->create(['prenom' => "Face{$i}"]);
            $faceUser = User::factory()->create([
                'userable_type' => Face::class,
                'userable_id' => $face->id,
                'email' => "face{$i}@example.test",
            ]);
            $candidature = Candidature::factory()->create([
                'mission_id' => $mission->id,
                'face_id' => $face->id,
                'status' => CandidatureStatus::Confirmed,
            ]);
            $entry = MissionPaymentCandidature::create([
                'mission_payment_id' => $payment->id,
                'candidature_id' => $candidature->id,
                'face_id' => $face->id,
                'montant_face_recoit' => 90000,
                'escrow_status' => EscrowStatus::Locked,
                'locked_at' => now(),
            ]);

            $faces[] = [
                'candidature' => $candidature,
                'entry' => $entry,
                'faceUser' => $faceUser,
                'face' => $face,
            ];
        }

        return [$mission, $faces];
    }

    private function setEntryAbsent(MissionPaymentCandidature $entry, ?\DateTimeInterface $notifiedAt = null): void
    {
        $entry->update([
            'attendance_status' => AttendanceStatus::Absent,
            'notified_at' => $notifiedAt ?? now(),
        ]);
    }

    public function test_dispute_returns_200_and_flips_entry_to_disputed(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(1);
        $mission->update(['status' => MissionStatus::PendingAttendanceValidation]);
        $this->setEntryAbsent($faces[0]['entry'], now()->subHours(1));

        $response = $this->actingAs($faces[0]['faceUser'])->postJson(
            "/api/v1/face/missions/{$mission->uuid}/dispute-attendance",
        );

        $response->assertOk()
            ->assertJsonPath('data.entry.id', $faces[0]['entry']->id)
            ->assertJsonPath('data.entry.attendance_status', 'disputed')
            ->assertJsonPath('data.entry.escrow_status', 'locked')
            ->assertJsonPath('data.mission.id', $mission->uuid)
            ->assertJsonPath('data.mission.status', 'pending_attendance_validation')
            ->assertJsonPath('data.mission.status_label', 'En attente de validation des présences')
            ->assertJsonPath('message', 'Votre contestation a bien été enregistrée.');

        $notifiedAtPayload = $response->json('data.entry.notified_at');
        $this->assertIsString($notifiedAtPayload);
        $this->assertNotSame('', $notifiedAtPayload);
        $this->assertNotNull(\Carbon\CarbonImmutable::parse($notifiedAtPayload));

        $this->assertDatabaseHas('mission_payment_candidatures', [
            'id' => $faces[0]['entry']->id,
            'attendance_status' => 'disputed',
            'escrow_status' => 'locked',
        ]);
        $this->assertSame(0, FinancialEvent::count());
    }

    public function test_dispute_returns_422_when_window_expired(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(1);
        $mission->update(['status' => MissionStatus::PendingAttendanceValidation]);
        $this->setEntryAbsent($faces[0]['entry'], now()->subHours(73));

        $this->actingAs($faces[0]['faceUser'])->postJson(
            "/api/v1/face/missions/{$mission->uuid}/dispute-attendance",
        )
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.attendance.0', 'Le délai de contestation (72h) est dépassé.');

        $this->assertSame(AttendanceStatus::Absent, $faces[0]['entry']->fresh()->attendance_status);
    }

    public function test_dispute_returns_422_when_face_has_no_entry_on_mission(): void
    {
        [$mission] = $this->createPaidMissionWithFaces(1);
        $strangerFace = Face::factory()->create();
        $strangerUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $strangerFace->id,
        ]);

        $this->actingAs($strangerUser)->postJson(
            "/api/v1/face/missions/{$mission->uuid}/dispute-attendance",
        )
            ->assertStatus(422)
            ->assertJsonPath('error.details.attendance.0', 'Aucune absence à contester sur cette mission.');
    }

    public function test_dispute_returns_422_when_entry_is_pending(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(1);

        $this->actingAs($faces[0]['faceUser'])->postJson(
            "/api/v1/face/missions/{$mission->uuid}/dispute-attendance",
        )
            ->assertStatus(422)
            ->assertJsonPath('error.details.attendance.0', 'Aucune absence à contester sur cette mission.');
    }

    public function test_dispute_returns_422_when_entry_is_present(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(1);
        $faces[0]['entry']->update([
            'attendance_status' => AttendanceStatus::Present,
            'escrow_status' => EscrowStatus::Released,
            'released_at' => now(),
        ]);

        $this->actingAs($faces[0]['faceUser'])->postJson(
            "/api/v1/face/missions/{$mission->uuid}/dispute-attendance",
        )
            ->assertStatus(422)
            ->assertJsonPath('error.details.attendance.0', 'Aucune absence à contester sur cette mission.');
    }

    public function test_dispute_returns_422_when_entry_is_already_disputed(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(1);
        $faces[0]['entry']->update([
            'attendance_status' => AttendanceStatus::Disputed,
            'notified_at' => now()->subHours(1),
        ]);

        $this->actingAs($faces[0]['faceUser'])->postJson(
            "/api/v1/face/missions/{$mission->uuid}/dispute-attendance",
        )
            ->assertStatus(422)
            ->assertJsonPath('error.details.attendance.0', 'Aucune absence à contester sur cette mission.');
    }

    public function test_dispute_returns_403_for_producer_user(): void
    {
        [$mission] = $this->createPaidMissionWithFaces(1);

        $this->actingAs($this->producerUser)->postJson(
            "/api/v1/face/missions/{$mission->uuid}/dispute-attendance",
        )
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_dispute_returns_401_for_unauthenticated_user(): void
    {
        [$mission] = $this->createPaidMissionWithFaces(1);

        $this->postJson("/api/v1/face/missions/{$mission->uuid}/dispute-attendance")
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_dispute_returns_404_for_unknown_mission_uuid(): void
    {
        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $this->actingAs($faceUser)->postJson(
            '/api/v1/face/missions/00000000-0000-0000-0000-000000000000/dispute-attendance',
        )
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_dispute_does_not_let_face_x_dispute_face_y_entry(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(2);
        $mission->update(['status' => MissionStatus::PendingAttendanceValidation]);
        $this->setEntryAbsent($faces[1]['entry'], now()->subHours(1));

        $this->actingAs($faces[0]['faceUser'])->postJson(
            "/api/v1/face/missions/{$mission->uuid}/dispute-attendance",
        )
            ->assertStatus(422)
            ->assertJsonPath('error.details.attendance.0', 'Aucune absence à contester sur cette mission.');

        $this->assertSame(AttendanceStatus::Absent, $faces[1]['entry']->fresh()->attendance_status);
    }
}
