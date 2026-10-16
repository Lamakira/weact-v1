<?php

declare(strict_types=1);

namespace Tests\Feature\Candidature;

use App\Enums\AttendanceStatus;
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
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Exposition de l'absence contestable (fenêtre 72 h après notified_at) dans les
 * ressources candidature Face : liste (FaceCandidatureResource) et détail mission
 * (CandidatureResource).
 */
class FaceCandidatureAttendanceContestTest extends TestCase
{
    use RefreshDatabase;

    private Face $face;

    private User $faceUser;

    private Mission $mission;

    private Candidature $candidature;

    private MissionPaymentCandidature $entry;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-09 10:00:00');

        $producer = Producer::factory()->create();
        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
        $this->mission = Mission::factory()->closed()->create(['producer_id' => $producer->id]);
        $payment = MissionPayment::create([
            'mission_id' => $this->mission->id,
            'producer_id' => $producer->id,
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
        $this->candidature = Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $this->face->id,
            'status' => CandidatureStatus::Confirmed,
        ]);
        $this->entry = MissionPaymentCandidature::create([
            'mission_payment_id' => $payment->id,
            'candidature_id' => $this->candidature->id,
            'face_id' => $this->face->id,
            'montant_face_recoit' => 90000,
            'escrow_status' => EscrowStatus::Locked,
            'locked_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function markAbsent(AttendanceStatus $status, string $notifiedAt): void
    {
        $this->entry->update(['attendance_status' => $status, 'notified_at' => $notifiedAt]);
    }

    public function test_list_exposes_contest_deadline_notified_at_plus_72h(): void
    {
        $this->markAbsent(AttendanceStatus::Absent, '2026-10-09 08:00:00');

        $response = $this->actingAs($this->faceUser)->getJson('/api/v1/face/candidatures')->assertOk();

        $response->assertJsonPath('data.0.attendance_dispute.state', 'contestable')
            ->assertJsonPath('data.0.attendance_dispute.can_contest', true);
        $this->assertTrue(
            Carbon::parse($response->json('data.0.attendance_dispute.deadline'))
                ->equalTo(Carbon::parse('2026-10-12 08:00:00')),
        );
    }

    public function test_detail_candidature_exposes_contest_deadline(): void
    {
        $this->markAbsent(AttendanceStatus::Absent, '2026-10-09 08:00:00');

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$this->mission->uuid}")
            ->assertOk();

        $response->assertJsonPath('candidature.attendance_dispute.state', 'contestable');
        $this->assertTrue(
            Carbon::parse($response->json('candidature.attendance_dispute.deadline'))
                ->equalTo(Carbon::parse('2026-10-12 08:00:00')),
        );
    }

    public function test_already_contested_is_exposed_as_contested(): void
    {
        $this->markAbsent(AttendanceStatus::Disputed, '2026-10-09 08:00:00');

        $this->actingAs($this->faceUser)->getJson('/api/v1/face/candidatures')
            ->assertOk()
            ->assertJsonPath('data.0.attendance_dispute.state', 'contested')
            ->assertJsonPath('data.0.attendance_dispute.can_contest', false);
    }

    public function test_expired_window_exposes_no_dispute(): void
    {
        $this->markAbsent(AttendanceStatus::Absent, '2026-10-05 08:00:00');

        $this->actingAs($this->faceUser)->getJson('/api/v1/face/candidatures')
            ->assertOk()
            ->assertJsonPath('data.0.attendance_dispute', null);
    }

    public function test_pending_attendance_exposes_no_dispute(): void
    {
        $this->actingAs($this->faceUser)->getJson('/api/v1/face/candidatures')
            ->assertOk()
            ->assertJsonPath('data.0.attendance_dispute', null);
    }
}
