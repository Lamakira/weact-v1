<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\AttendanceStatus;
use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Models\Candidature;
use App\Models\Deliverable;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Lot C1 : `summary` sur GET /producer/missions/{mission} (barre d'étapes en un appel).
 */
class ProducerMissionSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    /** @param array<string, mixed> $attrs */
    private function mission(MissionStatus $status, array $attrs = []): Mission
    {
        return Mission::factory()->create(array_merge([
            'producer_id' => $this->producer->id,
            'status' => $status,
            'nombre_faces_voulu' => 3,
            'date_tournage' => now()->addDays(10)->toDateString(),
        ], $attrs));
    }

    private function ugcMission(MissionStatus $status): Mission
    {
        return $this->mission($status, [
            'type_mission' => 'ugc',
            'commission_ugc' => 5000,
            'date_tournage' => null,
            'date_limite_candidature' => now()->addDays(5)->toDateString(),
        ]);
    }

    private function candidature(Mission $mission, CandidatureStatus $status): Candidature
    {
        return Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => Face::factory()->create()->id,
            'status' => $status,
        ]);
    }

    private function payment(Mission $mission, MissionPaymentStatus $status, int $faces = 1): MissionPayment
    {
        return MissionPayment::create([
            'mission_id' => $mission->id,
            'producer_id' => $this->producer->id,
            'nombre_faces_retenues' => $faces,
            'budget_par_face' => 100000,
            'montant_sous_total' => 100000 * $faces,
            'commission_producteur' => 10000 * $faces,
            'montant_total_producteur' => 110000 * $faces,
            'commission_faces_total' => 10000 * $faces,
            'montant_total_faces' => 90000 * $faces,
            'status' => $status,
            'paid_at' => $status === MissionPaymentStatus::Paid ? now()->subDay() : null,
        ]);
    }

    private function entry(MissionPayment $payment, Candidature $candidature, EscrowStatus $escrow = EscrowStatus::Locked, AttendanceStatus $attendance = AttendanceStatus::Pending): MissionPaymentCandidature
    {
        return MissionPaymentCandidature::create([
            'mission_payment_id' => $payment->id,
            'candidature_id' => $candidature->id,
            'face_id' => $candidature->face_id,
            'montant_face_recoit' => 90000,
            'escrow_status' => $escrow,
            'attendance_status' => $attendance,
            'locked_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function summary(Mission $mission): array
    {
        return $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$mission->uuid}")
            ->assertOk()
            ->json('data.summary');
    }

    public function test_non_owner_producer_gets_403_and_no_summary(): void
    {
        $mission = $this->mission(MissionStatus::Published);
        $otherProducer = Producer::factory()->create();
        $other = User::factory()->create(['userable_type' => Producer::class, 'userable_id' => $otherProducer->id]);

        $this->actingAs($other)->getJson("/api/v1/producer/missions/{$mission->uuid}")->assertForbidden();
    }

    public function test_summary_is_absent_from_the_list_endpoint(): void
    {
        $this->mission(MissionStatus::Published);

        $row = $this->actingAs($this->producerUser)->getJson('/api/v1/producer/missions')->assertOk()->json('data.0');

        $this->assertArrayNotHasKey('summary', $row);
    }

    public function test_candidature_counts_cover_every_status(): void
    {
        $mission = $this->mission(MissionStatus::Published);
        $this->candidature($mission, CandidatureStatus::Pending);
        $this->candidature($mission, CandidatureStatus::Pending);
        $this->candidature($mission, CandidatureStatus::Accepted);
        $this->candidature($mission, CandidatureStatus::Rejected);

        $summary = $this->summary($mission);

        $this->assertSame([
            'pending' => 2, 'accepted' => 1, 'confirmed' => 0, 'in_progress' => 0,
            'completed' => 0, 'rejected' => 1, 'cancelled' => 0, 'expired' => 0,
        ], $summary['candidatures']);
    }

    public function test_selection_and_payment_fields_for_a_paid_mission(): void
    {
        $mission = $this->mission(MissionStatus::Closed);
        $payment = $this->payment($mission, MissionPaymentStatus::Paid, 3);
        $this->entry($payment, $this->candidature($mission, CandidatureStatus::Confirmed));
        $this->entry($payment, $this->candidature($mission, CandidatureStatus::Accepted));
        $freed = $this->entry($payment, $this->candidature($mission, CandidatureStatus::Expired));
        $freed->update(['freed_at' => now()]);

        $summary = $this->summary($mission);

        $this->assertSame(['faces_wanted' => 3, 'faces_selected' => 3, 'faces_confirmed' => 1, 'freed_places' => 1], $summary['selection']);
        $this->assertSame('paid', $summary['payment']['status']);
        $this->assertNull($summary['payment']['expires_at']);
        $this->assertNotNull($summary['payment']['paid_at']);
        $this->assertSame(330000, $summary['payment']['montant_total_producteur']);
    }

    public function test_pending_payment_exposes_expiry_and_has_no_attendance(): void
    {
        $mission = $this->mission(MissionStatus::PendingPayment);
        $payment = $this->payment($mission, MissionPaymentStatus::Pending);
        $this->entry($payment, $this->candidature($mission, CandidatureStatus::Accepted));

        $summary = $this->summary($mission);

        $this->assertSame('pending', $summary['payment']['status']);
        $this->assertSame(
            $payment->created_at->copy()->addHours(\App\Services\MissionPaymentService::SELECTION_PAYMENT_WINDOW_HOURS)->toIso8601String(),
            $summary['payment']['expires_at'],
        );
        $this->assertNull($summary['payment']['paid_at']);
        $this->assertNull($summary['attendance']);
        $this->assertSame(1, $summary['selection']['faces_selected']);
    }

    public function test_mission_without_payment_has_null_payment_fields(): void
    {
        $summary = $this->summary($this->mission(MissionStatus::Published));

        $this->assertSame(['status' => null, 'expires_at' => null, 'paid_at' => null, 'montant_total_producteur' => null], $summary['payment']);
        $this->assertNull($summary['attendance']);
        $this->assertNull($summary['ugc_deliverables']);
    }

    public function test_attendance_counts_exclude_expired_and_unlocked_entries(): void
    {
        $mission = $this->mission(MissionStatus::Closed, ['date_tournage' => now()->subDay()->toDateString()]);
        $payment = $this->payment($mission, MissionPaymentStatus::Paid, 5);
        $this->entry($payment, $this->candidature($mission, CandidatureStatus::Confirmed));
        $this->entry($payment, $this->candidature($mission, CandidatureStatus::Confirmed), EscrowStatus::Released, AttendanceStatus::Present);
        $this->entry($payment, $this->candidature($mission, CandidatureStatus::Confirmed), EscrowStatus::Locked, AttendanceStatus::Absent);
        $this->entry($payment, $this->candidature($mission, CandidatureStatus::Confirmed), EscrowStatus::Locked, AttendanceStatus::Disputed);
        // Expirée : exclue du comptage.
        $this->entry($payment, $this->candidature($mission, CandidatureStatus::Expired), EscrowStatus::Refunded);
        // Transférée : exclue (ni Locked, Released, Refunded).
        $this->entry($payment, $this->candidature($mission, CandidatureStatus::Confirmed), EscrowStatus::Transferred);

        $attendance = $this->summary($mission)['attendance'];

        $this->assertSame(1, $attendance['pending']);
        $this->assertSame(1, $attendance['present']);
        $this->assertSame(1, $attendance['absent']);
        $this->assertSame(1, $attendance['disputed']);
        $this->assertTrue($attendance['can_mark']);
        $this->assertNotNull($attendance['opens_at']);
    }

    public function test_attendance_cannot_be_marked_before_the_shooting_day(): void
    {
        $mission = $this->mission(MissionStatus::Closed);
        $payment = $this->payment($mission, MissionPaymentStatus::Paid);
        $this->entry($payment, $this->candidature($mission, CandidatureStatus::Confirmed));

        $attendance = $this->summary($mission)['attendance'];

        $this->assertFalse($attendance['can_mark']);
        $this->assertSame(
            $mission->shootingDayStartsAt()->toIso8601String(),
            $attendance['opens_at'],
        );
    }

    public function test_ugc_deliverables_are_counted_for_ugc_missions(): void
    {
        $mission = $this->ugcMission(MissionStatus::Closed);
        $a = $this->candidature($mission, CandidatureStatus::InProgress);
        $b = $this->candidature($mission, CandidatureStatus::Completed);
        Deliverable::factory()->for($a, 'owner')->create();
        Deliverable::factory()->for($a, 'owner')->avis()->validated()->create();
        Deliverable::factory()->for($b, 'owner')->validated()->create();
        Deliverable::factory()->for($b, 'owner')->avis()->retoucheRequested()->create();
        // Autre mission : ne doit pas être comptée.
        $otherMission = $this->ugcMission(MissionStatus::Closed);
        Deliverable::factory()->for($this->candidature($otherMission, CandidatureStatus::InProgress), 'owner')->create();

        $this->assertSame(
            ['to_review' => 1, 'validated' => 2, 'retouche_requested' => 1],
            $this->summary($mission)['ugc_deliverables'],
        );
    }

    public function test_completion_blocker_exposes_the_reason(): void
    {
        $blocked = $this->mission(MissionStatus::Closed);
        $summary = $this->summary($blocked);
        $this->assertSame('Le paiement doit être confirmé avant de terminer la mission', $summary['completion_blocker']);

        $ready = $this->mission(MissionStatus::Closed);
        $payment = $this->payment($ready, MissionPaymentStatus::Paid);
        $this->entry($payment, $this->candidature($ready, CandidatureStatus::Confirmed));
        $this->assertNull($this->summary($ready)['completion_blocker']);
    }

    public function test_can_edit_matches_the_frontend_rule(): void
    {
        $this->assertTrue($this->summary($this->mission(MissionStatus::Draft))['can_edit']);
        $this->assertTrue($this->summary($this->mission(MissionStatus::Published))['can_edit']);
        $this->assertFalse($this->summary($this->mission(MissionStatus::Closed))['can_edit']);
        $this->assertFalse($this->summary($this->mission(MissionStatus::PendingPayment))['can_edit']);
        $this->assertFalse($this->summary($this->ugcMission(MissionStatus::Published))['can_edit']);
        $this->assertFalse($this->summary($this->ugcMission(MissionStatus::Draft))['can_edit']);
    }

    public function test_step_recruiting_for_published_and_for_manually_closed_without_payment(): void
    {
        $this->assertSame('recruiting', $this->summary($this->mission(MissionStatus::Published))['step']);
        $this->assertSame('recruiting', $this->summary($this->mission(MissionStatus::Closed))['step']);
    }

    public function test_step_payment(): void
    {
        $this->assertSame('payment', $this->summary($this->mission(MissionStatus::PendingPayment))['step']);
    }

    public function test_step_confirmations_when_a_selected_face_is_still_accepted(): void
    {
        $mission = $this->mission(MissionStatus::Closed);
        $payment = $this->payment($mission, MissionPaymentStatus::Paid, 2);
        $this->entry($payment, $this->candidature($mission, CandidatureStatus::Confirmed));
        $this->entry($payment, $this->candidature($mission, CandidatureStatus::Accepted));

        $this->assertSame('confirmations', $this->summary($mission)['step']);
    }

    public function test_step_ready_when_everyone_confirmed_before_shooting_day(): void
    {
        $mission = $this->mission(MissionStatus::Closed);
        $payment = $this->payment($mission, MissionPaymentStatus::Paid, 2);
        $this->entry($payment, $this->candidature($mission, CandidatureStatus::Confirmed));
        $this->entry($payment, $this->candidature($mission, CandidatureStatus::Confirmed));

        $this->assertSame('ready', $this->summary($mission)['step']);
    }

    public function test_step_attendance_on_shooting_day_and_when_pending_validation(): void
    {
        $today = $this->mission(MissionStatus::Closed, ['date_tournage' => now()->toDateString()]);
        $payment = $this->payment($today, MissionPaymentStatus::Paid);
        $this->entry($payment, $this->candidature($today, CandidatureStatus::Confirmed));
        $this->assertSame('attendance', $this->summary($today)['step']);

        $pending = $this->mission(MissionStatus::PendingAttendanceValidation, ['date_tournage' => now()->subDay()->toDateString()]);
        $this->assertSame('attendance', $this->summary($pending)['step']);
    }

    public function test_step_completed(): void
    {
        $this->assertSame('completed', $this->summary($this->mission(MissionStatus::Completed))['step']);
        $this->assertSame('completed', $this->summary($this->ugcMission(MissionStatus::Completed))['step']);
    }

    public function test_ugc_steps(): void
    {
        $this->assertSame('commission', $this->summary($this->ugcMission(MissionStatus::PendingPayment))['step']);
        $this->assertSame('recruiting', $this->summary($this->ugcMission(MissionStatus::Published))['step']);
        $this->assertSame('tunnel', $this->summary($this->ugcMission(MissionStatus::Closed))['step']);

        // Recrutement fini (date limite passée) avec une Face engagée : tunnel.
        $over = $this->ugcMission(MissionStatus::Published);
        $over->update(['date_limite_candidature' => now()->subDays(3)->toDateString()]);
        $this->candidature($over, CandidatureStatus::InProgress);
        $this->assertSame('tunnel', $this->summary($over)['step']);
    }

    /**
     * Mesuré : 20 requêtes (≈ 17 avant C1 : auth, mission, ratings Producer, completionBlocker...).
     * Le résumé ajoute 3 requêtes ; completionBlocker et has_paid_payment ne sont plus rejoués.
     */
    public function test_show_query_count_is_bounded(): void
    {
        $mission = $this->mission(MissionStatus::Closed, ['date_tournage' => now()->subDay()->toDateString()]);
        $payment = $this->payment($mission, MissionPaymentStatus::Paid, 3);
        for ($i = 0; $i < 3; $i++) {
            $this->entry($payment, $this->candidature($mission, CandidatureStatus::Confirmed));
        }

        $this->actingAs($this->producerUser);
        DB::enableQueryLog();
        $this->getJson("/api/v1/producer/missions/{$mission->uuid}")->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(20, $count, "show a exécuté {$count} requêtes");
    }
}
