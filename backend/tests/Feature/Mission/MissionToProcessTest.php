<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\AttendanceStatus;
use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Models\Candidature;
use App\Models\Deliverable;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Lot D7 : filtre « À traiter » des missions d'un Producteur (Mission::toProcessForProducer),
 * exposé par GET /api/v1/producer/missions?status=to_process.
 * Instant figé : mardi 20/10/2026 10:00 UTC (11:00 heure métier, UTC+1).
 */
class MissionToProcessTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private User $producerUser;

    private Producer $otherProducer;

    private User $otherProducerUser;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-20 10:00:00');

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create(['userable_type' => Producer::class, 'userable_id' => $this->producer->id]);
        $this->otherProducer = Producer::factory()->create();
        $this->otherProducerUser = User::factory()->create(['userable_type' => Producer::class, 'userable_id' => $this->otherProducer->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function mission(MissionStatus $status, array $attrs = []): Mission
    {
        return Mission::factory()->create(array_merge([
            'producer_id' => $this->producer->id,
            'status' => $status,
            'type_mission' => MissionType::Publicite,
            'date_tournage' => '2026-11-15',
            'date_limite_candidature' => '2026-10-25',
        ], $attrs));
    }

    private function candidature(Mission $mission, CandidatureStatus $status): Candidature
    {
        return Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => Face::factory()->create()->id,
            'status' => $status,
            'accepted_at' => $status === CandidatureStatus::Pending ? null : now()->subDay(),
        ]);
    }

    private function payment(Mission $mission, MissionPaymentStatus $status, ?Carbon $createdAt = null): MissionPayment
    {
        $payment = MissionPayment::create([
            'mission_id' => $mission->id,
            'producer_id' => $mission->producer_id,
            'nombre_faces_retenues' => 1,
            'budget_par_face' => 100000,
            'montant_sous_total' => 100000,
            'commission_producteur' => 10000,
            'montant_total_producteur' => 110000,
            'commission_faces_total' => 10000,
            'montant_total_faces' => 90000,
            'status' => $status,
            'paid_at' => $status === MissionPaymentStatus::Paid ? now()->subDay() : null,
        ]);

        if ($createdAt !== null) {
            DB::table('mission_payments')->where('id', $payment->id)->update(['created_at' => $createdAt]);
        }

        return $payment;
    }

    private function entry(
        MissionPayment $payment,
        Candidature $candidature,
        EscrowStatus $escrow = EscrowStatus::Locked,
        AttendanceStatus $attendance = AttendanceStatus::Pending,
    ): MissionPaymentCandidature {
        return MissionPaymentCandidature::create([
            'mission_payment_id' => $payment->id,
            'candidature_id' => $candidature->id,
            'face_id' => $candidature->face_id,
            'montant_face_recoit' => 90000,
            'escrow_status' => $escrow,
            'attendance_status' => $attendance,
            'locked_at' => now()->subDay(),
        ]);
    }

    private function inProcess(Mission $mission, ?Producer $producer = null): bool
    {
        return Mission::query()->toProcessForProducer(($producer ?? $this->producer)->id)->whereKey($mission->id)->exists();
    }

    public function test_pending_candidatures_to_answer_while_response_window_is_open(): void
    {
        $open = $this->mission(MissionStatus::Published);
        $this->candidature($open, CandidatureStatus::Pending);

        $onlyRejected = $this->mission(MissionStatus::Published);
        $this->candidature($onlyRejected, CandidatureStatus::Rejected);

        // Candidatures closes le 18/10 : réponse due 21/10 23:00 UTC (fin du 18 + 72 h), pas encore écoulée.
        $recentlyClosed = $this->mission(MissionStatus::Published, ['date_limite_candidature' => '2026-10-18']);
        $this->candidature($recentlyClosed, CandidatureStatus::Pending);

        // Candidatures closes le 16/10 : réponse due le 19/10 23:00 UTC, écoulée.
        $elapsed = $this->mission(MissionStatus::Published, ['date_limite_candidature' => '2026-10-16']);
        $this->candidature($elapsed, CandidatureStatus::Pending);

        // Le tournage (00:00 heure métier le 20/10 = 19/10 23:00 UTC) arrive avant l'échéance : écoulée.
        $shootBeforeDeadline = $this->mission(MissionStatus::Published, [
            'date_limite_candidature' => '2026-10-18',
            'date_tournage' => '2026-10-20',
        ]);
        $this->candidature($shootBeforeDeadline, CandidatureStatus::Pending);

        $closed = $this->mission(MissionStatus::Closed);
        $this->candidature($closed, CandidatureStatus::Pending);

        $this->assertTrue($this->inProcess($open));
        $this->assertFalse($this->inProcess($onlyRejected));
        $this->assertTrue($this->inProcess($recentlyClosed));
        $this->assertFalse($this->inProcess($elapsed));
        $this->assertFalse($this->inProcess($shootBeforeDeadline));
        $this->assertFalse($this->inProcess($closed));
    }

    public function test_selection_to_pay_while_payment_window_is_open(): void
    {
        $open = $this->mission(MissionStatus::PendingPayment);
        $this->payment($open, MissionPaymentStatus::Pending, now()->subHours(2));

        $elapsed = $this->mission(MissionStatus::PendingPayment);
        $this->payment($elapsed, MissionPaymentStatus::Pending, now()->subHours(25));

        $failed = $this->mission(MissionStatus::PendingPayment);
        $this->payment($failed, MissionPaymentStatus::Failed, now()->subHours(2));

        $noPayment = $this->mission(MissionStatus::PendingPayment);

        $this->assertTrue($this->inProcess($open));
        $this->assertFalse($this->inProcess($elapsed));
        $this->assertFalse($this->inProcess($failed));
        $this->assertFalse($this->inProcess($noPayment));
    }

    public function test_ugc_commission_to_pay(): void
    {
        $ugcPending = $this->mission(MissionStatus::PendingPayment, [
            'type_mission' => MissionType::Ugc,
            'date_tournage' => null,
        ]);
        $ugcPublished = $this->mission(MissionStatus::Published, [
            'type_mission' => MissionType::Ugc,
            'date_tournage' => null,
        ]);

        $this->assertTrue($this->inProcess($ugcPending));
        $this->assertFalse($this->inProcess($ugcPublished));
    }

    public function test_attendance_to_validate_once_shooting_day_is_reached(): void
    {
        $shot = $this->mission(MissionStatus::Closed, ['date_tournage' => '2026-10-19']);
        $this->entry($this->payment($shot, MissionPaymentStatus::Paid), $this->candidature($shot, CandidatureStatus::Confirmed));

        // Jour de tournage = aujourd'hui (00:00 heure métier déjà passé).
        $shootToday = $this->mission(MissionStatus::Closed, ['date_tournage' => '2026-10-20']);
        $this->entry($this->payment($shootToday, MissionPaymentStatus::Paid), $this->candidature($shootToday, CandidatureStatus::Confirmed));

        $inValidation = $this->mission(MissionStatus::PendingAttendanceValidation, ['date_tournage' => '2026-10-19']);
        $this->entry($this->payment($inValidation, MissionPaymentStatus::Paid), $this->candidature($inValidation, CandidatureStatus::Confirmed));

        $shootFuture = $this->mission(MissionStatus::Closed, ['date_tournage' => '2026-10-30']);
        $this->entry($this->payment($shootFuture, MissionPaymentStatus::Paid), $this->candidature($shootFuture, CandidatureStatus::Confirmed));

        $allMarked = $this->mission(MissionStatus::Closed, ['date_tournage' => '2026-10-19']);
        $this->entry(
            $this->payment($allMarked, MissionPaymentStatus::Paid),
            $this->candidature($allMarked, CandidatureStatus::Confirmed),
            attendance: AttendanceStatus::Present,
        );

        $released = $this->mission(MissionStatus::Closed, ['date_tournage' => '2026-10-19']);
        $this->entry(
            $this->payment($released, MissionPaymentStatus::Paid),
            $this->candidature($released, CandidatureStatus::Completed),
            escrow: EscrowStatus::Released,
        );

        $expiredFace = $this->mission(MissionStatus::Closed, ['date_tournage' => '2026-10-19']);
        $this->entry($this->payment($expiredFace, MissionPaymentStatus::Paid), $this->candidature($expiredFace, CandidatureStatus::Expired));

        $unpaid = $this->mission(MissionStatus::Closed, ['date_tournage' => '2026-10-19']);
        $this->entry($this->payment($unpaid, MissionPaymentStatus::Pending), $this->candidature($unpaid, CandidatureStatus::Confirmed));

        $this->assertTrue($this->inProcess($shot));
        $this->assertTrue($this->inProcess($shootToday));
        $this->assertTrue($this->inProcess($inValidation));
        $this->assertFalse($this->inProcess($shootFuture));
        $this->assertFalse($this->inProcess($allMarked));
        $this->assertFalse($this->inProcess($released));
        $this->assertFalse($this->inProcess($expiredFace));
        $this->assertFalse($this->inProcess($unpaid));
    }

    public function test_ugc_deliverables_to_validate(): void
    {
        $inReview = $this->mission(MissionStatus::Closed, ['type_mission' => MissionType::Ugc, 'date_tournage' => null]);
        Deliverable::factory()->unboxing()->create($this->owner($this->candidature($inReview, CandidatureStatus::Confirmed)));

        $validated = $this->mission(MissionStatus::Closed, ['type_mission' => MissionType::Ugc, 'date_tournage' => null]);
        Deliverable::factory()->unboxing()->validated()->create($this->owner($this->candidature($validated, CandidatureStatus::Confirmed)));

        $retouche = $this->mission(MissionStatus::Closed, ['type_mission' => MissionType::Ugc, 'date_tournage' => null]);
        Deliverable::factory()->unboxing()->retoucheRequested()->create($this->owner($this->candidature($retouche, CandidatureStatus::Confirmed)));

        $completed = $this->mission(MissionStatus::Completed, ['type_mission' => MissionType::Ugc, 'date_tournage' => null]);
        Deliverable::factory()->avis()->create($this->owner($this->candidature($completed, CandidatureStatus::Completed)));

        // Un livrable « en revue » sur une mission standard n'existe pas dans le produit : jamais retenu.
        $standard = $this->mission(MissionStatus::Closed);
        Deliverable::factory()->unboxing()->create($this->owner($this->candidature($standard, CandidatureStatus::Confirmed)));

        $this->assertTrue($this->inProcess($inReview));
        $this->assertFalse($this->inProcess($validated));
        $this->assertFalse($this->inProcess($retouche));
        $this->assertFalse($this->inProcess($completed));
        $this->assertFalse($this->inProcess($standard));
    }

    /**
     * @return array<string, mixed>
     */
    private function owner(Candidature $candidature): array
    {
        return ['owner_type' => $candidature->getMorphClass(), 'owner_id' => $candidature->id];
    }

    public function test_scope_is_isolated_between_producers(): void
    {
        $mine = $this->mission(MissionStatus::PendingPayment, ['type_mission' => MissionType::Ugc, 'date_tournage' => null]);

        $this->assertTrue($this->inProcess($mine));
        $this->assertFalse($this->inProcess($mine, $this->otherProducer));
    }

    public function test_api_status_filter_to_process_returns_only_the_producers_actionable_missions(): void
    {
        $actionable = $this->mission(MissionStatus::PendingPayment, ['type_mission' => MissionType::Ugc, 'date_tournage' => null]);
        $this->mission(MissionStatus::Draft);
        $this->mission(MissionStatus::Completed);
        $others = Mission::factory()->create([
            'producer_id' => $this->otherProducer->id,
            'status' => MissionStatus::PendingPayment,
            'type_mission' => MissionType::Ugc,
            'date_tournage' => null,
        ]);

        $uuids = collect(
            $this->actingAs($this->producerUser)->getJson('/api/v1/producer/missions?status=to_process')->assertOk()->json('data')
        )->pluck('id')->all();

        $this->assertSame([$actionable->uuid], $uuids);
        $otherUuids = collect(
            $this->actingAs($this->otherProducerUser)->getJson('/api/v1/producer/missions?status=to_process')->assertOk()->json('data')
        )->pluck('id')->all();
        $this->assertSame([$others->uuid], $otherUuids);
    }

    public function test_api_status_filter_still_rejects_unknown_values(): void
    {
        $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/missions?status=nope')
            ->assertStatus(422);
    }
}
