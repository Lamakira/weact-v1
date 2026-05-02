<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\AttendanceStatus;
use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\FinancialEventType;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Http\Resources\MissionAttendanceEntryResource;
use App\Mail\MissionCompletedMail;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\FinancialEvent;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProducerAttendanceEndpointsTest extends TestCase
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

    public function test_attendance_entry_resource_treats_legacy_null_attendance_status_as_pending(): void
    {
        $entry = new MissionPaymentCandidature([
            'montant_face_recoit' => 90000,
            'escrow_status' => EscrowStatus::Locked,
            'attendance_status' => null,
            'released_at' => null,
            'refunded_at' => null,
            'notified_at' => null,
        ]);
        $entry->id = 123;

        $payload = (new MissionAttendanceEntryResource($entry))->resolve(request());

        $this->assertSame('pending', $payload['attendance_status']);
        $this->assertSame('En attente', $payload['attendance_status_label']);
        $this->assertSame('locked', $payload['escrow_status']);
        $this->assertSame('Bloqué', $payload['escrow_status_label']);
    }

    /**
     * @return array{0: Mission, 1: list<array{candidature: Candidature, entry: MissionPaymentCandidature, faceUser: User, face: Face}>}
     */
    private function createPaidMissionWithFaces(
        int $faceCount,
        MissionStatus $missionStatus = MissionStatus::Closed,
    ): array {
        $factoryMethod = match ($missionStatus) {
            MissionStatus::Closed, MissionStatus::PendingAttendanceValidation => 'closed',
            default => throw new \InvalidArgumentException("Unsupported mission status for fixture: {$missionStatus->value}"),
        };

        /** @var Mission $mission */
        $mission = Mission::factory()->{$factoryMethod}()->create([
            'producer_id' => $this->producer->id,
        ]);

        if ($missionStatus === MissionStatus::PendingAttendanceValidation) {
            $mission->update(['status' => MissionStatus::PendingAttendanceValidation]);
            $mission->refresh();
        }

        /** @var MissionPayment $payment */
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
            /** @var Face $face */
            $face = Face::factory()->create([
                'prenom' => "Face{$i}",
                'nom' => 'Test',
            ]);

            /** @var User $faceUser */
            $faceUser = User::factory()->create([
                'userable_type' => Face::class,
                'userable_id' => $face->id,
                'email' => "mission{$mission->id}.face{$i}@example.test",
            ]);

            /** @var Candidature $candidature */
            $candidature = Candidature::factory()->create([
                'mission_id' => $mission->id,
                'face_id' => $face->id,
                'status' => CandidatureStatus::Confirmed,
            ]);

            /** @var MissionPaymentCandidature $entry */
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

    public function test_get_form_returns_entries_for_owner_producer(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(3);

        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$mission->uuid}/attendance-form");

        $response->assertOk()
            ->assertJsonPath('data.mission.id', $mission->uuid)
            ->assertJsonPath('data.mission.status', 'closed')
            ->assertJsonPath('data.mission.status_label', 'Clôturée')
            ->assertJsonPath('data.payment.montant_total_producteur', 330000)
            ->assertJsonPath('data.payment.nombre_faces_retenues', 3)
            ->assertJsonCount(3, 'data.entries')
            ->assertJsonPath('data.entries.0.id', $faces[0]['entry']->id)
            ->assertJsonPath('data.entries.0.face.id', $faces[0]['face']->uuid)
            ->assertJsonPath('data.entries.0.face.display_name', $faces[0]['face']->display_name)
            ->assertJsonPath('data.entries.0.montant_face_recoit', 90000)
            ->assertJsonPath('data.entries.0.attendance_status', 'pending')
            ->assertJsonPath('data.entries.0.attendance_status_label', 'En attente')
            ->assertJsonPath('data.entries.0.escrow_status', 'locked')
            ->assertJsonPath('data.entries.0.escrow_status_label', 'Bloqué')
            ->assertJsonPath('data.entries.0.notified_at', null);
    }

    public function test_get_form_works_for_pending_attendance_validation_status(): void
    {
        [$mission] = $this->createPaidMissionWithFaces(2, MissionStatus::PendingAttendanceValidation);

        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$mission->uuid}/attendance-form");

        $response->assertOk()
            ->assertJsonPath('data.mission.status', 'pending_attendance_validation')
            ->assertJsonPath('data.mission.status_label', 'En attente de validation des présences');
    }

    public function test_get_form_returns_403_for_non_owner(): void
    {
        [$mission] = $this->createPaidMissionWithFaces(1);
        $otherProducer = Producer::factory()->create();
        $otherProducerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $otherProducer->id,
        ]);

        $this->actingAs($otherProducerUser)
            ->getJson("/api/v1/producer/missions/{$mission->uuid}/attendance-form")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_get_form_returns_403_for_face_user(): void
    {
        [$mission] = $this->createPaidMissionWithFaces(1);
        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $this->actingAs($faceUser)
            ->getJson("/api/v1/producer/missions/{$mission->uuid}/attendance-form")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_get_form_returns_401_for_unauthenticated_user(): void
    {
        [$mission] = $this->createPaidMissionWithFaces(1);

        $this->getJson("/api/v1/producer/missions/{$mission->uuid}/attendance-form")
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_get_form_returns_404_for_unknown_uuid(): void
    {
        $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/missions/00000000-0000-0000-0000-000000000000/attendance-form')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_get_form_returns_422_for_invalid_mission_status(): void
    {
        $publishedMission = Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
        ]);

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$publishedMission->uuid}/attendance-form")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_get_form_returns_422_for_closed_mission_without_paid_payment(): void
    {
        $closedMission = Mission::factory()->closed()->create([
            'producer_id' => $this->producer->id,
        ]);

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$closedMission->uuid}/attendance-form")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_validate_attendance_completes_mission_with_all_present(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(2);

        $response = $this->actingAs($this->producerUser)->postJson(
            "/api/v1/producer/missions/{$mission->uuid}/validate-attendance",
            ['entries' => [
                ['entry_id' => $faces[0]['entry']->id, 'status' => 'present'],
                ['entry_id' => $faces[1]['entry']->id, 'status' => 'present'],
            ]],
        );

        $response->assertOk()
            ->assertJsonPath('data.mission.status', 'completed')
            ->assertJsonPath('data.mission.status_label', 'Terminée')
            ->assertJsonPath('message', 'Présences validées avec succès.')
            ->assertJsonCount(2, 'data.entries')
            ->assertJsonPath('data.entries.0.attendance_status', 'present')
            ->assertJsonPath('data.entries.0.escrow_status', 'released')
            ->assertJsonPath('data.entries.0.released_at', fn ($value): bool => is_string($value) && $value !== '')
            ->assertJsonPath('data.entries.0.refunded_at', null)
            ->assertJsonPath('data.entries.1.attendance_status', 'present')
            ->assertJsonPath('data.entries.1.escrow_status', 'released')
            ->assertJsonPath('data.entries.1.released_at', fn ($value): bool => is_string($value) && $value !== '');

        $this->assertSame(90000, $faces[0]['faceUser']->refresh()->balance);
        $this->assertSame(90000, $faces[1]['faceUser']->refresh()->balance);
        Mail::assertQueued(MissionCompletedMail::class, 2);
        $this->assertSame(2, FinancialEvent::where('type', FinancialEventType::EscrowRelease)->count());
        $this->assertTrue(
            Notification::where('user_id', $this->producerUser->id)
                ->where('type', 'mission_completed_producer')
                ->exists(),
        );
    }

    public function test_validate_attendance_keeps_absent_locked_and_mission_pending(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(3);

        $response = $this->actingAs($this->producerUser)->postJson(
            "/api/v1/producer/missions/{$mission->uuid}/validate-attendance",
            ['entries' => [
                ['entry_id' => $faces[0]['entry']->id, 'status' => 'present'],
                ['entry_id' => $faces[1]['entry']->id, 'status' => 'absent'],
                ['entry_id' => $faces[2]['entry']->id, 'status' => 'present'],
            ]],
        );

        $response->assertOk()
            ->assertJsonPath('data.mission.status', 'pending_attendance_validation')
            ->assertJsonPath('data.entries.1.attendance_status', 'absent')
            ->assertJsonPath('data.entries.1.escrow_status', 'locked')
            ->assertJsonPath('data.entries.1.released_at', null)
            ->assertJsonPath('data.entries.1.refunded_at', null)
            ->assertJsonPath('data.entries.1.notified_at', fn ($value): bool => is_string($value) && $value !== '');

        $entry1 = $faces[1]['entry']->fresh();
        $this->assertInstanceOf(MissionPaymentCandidature::class, $entry1);
        $this->assertSame(EscrowStatus::Locked, $entry1->escrow_status);
        $this->assertSame(AttendanceStatus::Absent, $entry1->attendance_status);
        $this->assertNotNull($entry1->notified_at);

        $this->assertSame(0, $faces[1]['faceUser']->refresh()->balance);
        Mail::assertQueued(MissionCompletedMail::class, 2);
        $this->assertSame(0, FinancialEvent::where('type', FinancialEventType::Refund)->count());
    }

    public function test_validate_attendance_with_partial_payload_returns_full_snapshot(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(3);

        $response = $this->actingAs($this->producerUser)->postJson(
            "/api/v1/producer/missions/{$mission->uuid}/validate-attendance",
            ['entries' => [['entry_id' => $faces[0]['entry']->id, 'status' => 'present']]],
        );

        $response->assertOk()
            ->assertJsonPath('data.mission.status', 'pending_attendance_validation')
            ->assertJsonCount(3, 'data.entries')
            ->assertJsonPath('data.entries.0.attendance_status', 'present')
            ->assertJsonPath('data.entries.1.attendance_status', 'pending')
            ->assertJsonPath('data.entries.2.attendance_status', 'pending');
    }

    public function test_validate_attendance_works_on_pending_attendance_validation_status(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(2, MissionStatus::PendingAttendanceValidation);

        $response = $this->actingAs($this->producerUser)->postJson(
            "/api/v1/producer/missions/{$mission->uuid}/validate-attendance",
            ['entries' => [
                ['entry_id' => $faces[0]['entry']->id, 'status' => 'present'],
                ['entry_id' => $faces[1]['entry']->id, 'status' => 'present'],
            ]],
        );

        $response->assertOk()->assertJsonPath('data.mission.status', 'completed');
    }

    public function test_validate_attendance_returns_403_for_non_owner_producer(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(1);
        $otherProducer = Producer::factory()->create();
        $otherProducerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $otherProducer->id,
        ]);

        $this->actingAs($otherProducerUser)->postJson(
            "/api/v1/producer/missions/{$mission->uuid}/validate-attendance",
            ['entries' => [['entry_id' => $faces[0]['entry']->id, 'status' => 'present']]],
        )->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');

        $entry = $faces[0]['entry']->fresh();
        $this->assertInstanceOf(MissionPaymentCandidature::class, $entry);
        $this->assertSame(AttendanceStatus::Pending, $entry->attendance_status);
    }

    public function test_validate_attendance_returns_401_for_unauthenticated(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(1);

        $this->postJson(
            "/api/v1/producer/missions/{$mission->uuid}/validate-attendance",
            ['entries' => [['entry_id' => $faces[0]['entry']->id, 'status' => 'present']]],
        )->assertStatus(401)->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_validate_attendance_returns_404_for_unknown_uuid(): void
    {
        $this->actingAs($this->producerUser)->postJson(
            '/api/v1/producer/missions/00000000-0000-0000-0000-000000000000/validate-attendance',
            ['entries' => [['entry_id' => 1, 'status' => 'present']]],
        )->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_validate_attendance_returns_422_for_invalid_mission_status(): void
    {
        $publishedMission = Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
        ]);

        $this->actingAs($this->producerUser)->postJson(
            "/api/v1/producer/missions/{$publishedMission->uuid}/validate-attendance",
            ['entries' => [['entry_id' => 1, 'status' => 'present']]],
        )->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_validate_attendance_returns_422_for_empty_entries(): void
    {
        [$mission] = $this->createPaidMissionWithFaces(1);

        $this->actingAs($this->producerUser)->postJson(
            "/api/v1/producer/missions/{$mission->uuid}/validate-attendance",
            ['entries' => []],
        )->assertStatus(422)->assertJsonValidationErrors(['entries']);
    }

    public function test_validate_attendance_returns_422_for_missing_entries_key(): void
    {
        [$mission] = $this->createPaidMissionWithFaces(1);

        $this->actingAs($this->producerUser)->postJson(
            "/api/v1/producer/missions/{$mission->uuid}/validate-attendance",
            [],
        )->assertStatus(422)->assertJsonValidationErrors(['entries']);
    }

    public function test_validate_attendance_returns_422_for_non_integer_entry_id(): void
    {
        [$mission] = $this->createPaidMissionWithFaces(1);

        $this->actingAs($this->producerUser)->postJson(
            "/api/v1/producer/missions/{$mission->uuid}/validate-attendance",
            ['entries' => [['entry_id' => 'abc', 'status' => 'present']]],
        )->assertStatus(422)->assertJsonValidationErrors(['entries.0.entry_id']);
    }

    public function test_validate_attendance_returns_422_for_invalid_status_value(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(1);

        $this->actingAs($this->producerUser)->postJson(
            "/api/v1/producer/missions/{$mission->uuid}/validate-attendance",
            ['entries' => [['entry_id' => $faces[0]['entry']->id, 'status' => 'disputed']]],
        )->assertStatus(422)->assertJsonValidationErrors(['entries.0.status']);
    }

    public function test_validate_attendance_returns_422_for_duplicate_entry_ids(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(1);

        $this->actingAs($this->producerUser)->postJson(
            "/api/v1/producer/missions/{$mission->uuid}/validate-attendance",
            ['entries' => [
                ['entry_id' => $faces[0]['entry']->id, 'status' => 'present'],
                ['entry_id' => $faces[0]['entry']->id, 'status' => 'absent'],
            ]],
        )->assertStatus(422)->assertJsonValidationErrors(['entries.0.entry_id']);

        $entry = $faces[0]['entry']->fresh();
        $this->assertInstanceOf(MissionPaymentCandidature::class, $entry);
        $this->assertSame(AttendanceStatus::Pending, $entry->attendance_status);
        $this->assertSame(EscrowStatus::Locked, $entry->escrow_status);
    }

    public function test_validate_attendance_returns_422_for_entry_belonging_to_another_mission(): void
    {
        [$mission1] = $this->createPaidMissionWithFaces(1);
        [, $faces2] = $this->createPaidMissionWithFaces(1);

        $this->actingAs($this->producerUser)->postJson(
            "/api/v1/producer/missions/{$mission1->uuid}/validate-attendance",
            ['entries' => [['entry_id' => $faces2[0]['entry']->id, 'status' => 'present']]],
        )->assertStatus(422)->assertJsonValidationErrors(['entries']);

        $entry = $faces2[0]['entry']->fresh();
        $this->assertInstanceOf(MissionPaymentCandidature::class, $entry);
        $this->assertSame(AttendanceStatus::Pending, $entry->attendance_status);
    }

    public function test_replay_on_completed_mission_returns_422(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(1);

        $this->actingAs($this->producerUser)->postJson(
            "/api/v1/producer/missions/{$mission->uuid}/validate-attendance",
            ['entries' => [['entry_id' => $faces[0]['entry']->id, 'status' => 'present']]],
        )->assertOk();

        $freshMission = $mission->fresh();
        $this->assertInstanceOf(Mission::class, $freshMission);
        $this->assertSame(MissionStatus::Completed, $freshMission->status);

        $response = $this->actingAs($this->producerUser)->postJson(
            "/api/v1/producer/missions/{$mission->uuid}/validate-attendance",
            ['entries' => [['entry_id' => $faces[0]['entry']->id, 'status' => 'present']]],
        );

        $response->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertSame(90000, $faces[0]['faceUser']->refresh()->balance);
        $this->assertSame(1, FinancialEvent::where('type', FinancialEventType::EscrowRelease)->count());
        Mail::assertQueued(MissionCompletedMail::class, 1);
    }
}
