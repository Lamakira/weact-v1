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
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\User;
use App\Services\MissionAttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Lot B5 — les présences ne se pointent qu'à partir du jour du tournage (fuseau métier),
 * et seulement pour une Face qui a confirmé sa participation (candidature confirmed ou
 * in_progress). Garde backend (FormRequest + service sous verrou) et raison exposée au front.
 */
class AttendanceFromShootingDayTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private User $producerUser;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        // Lundi 26/10/2026 12:00 UTC.
        $this->travelTo(Carbon::parse('2026-10-26 12:00:00', 'UTC'));

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    /**
     * Mission clôturée et payée, une entry Locked par statut de candidature donné.
     *
     * @param  list<CandidatureStatus>  $candidatureStatuses
     * @return array{0: Mission, 1: list<MissionPaymentCandidature>}
     */
    private function closedPaidMission(string $dateTournage, array $candidatureStatuses = [CandidatureStatus::Confirmed]): array
    {
        $mission = Mission::factory()->closed()->create([
            'producer_id' => $this->producer->id,
            'date_tournage' => $dateTournage,
            'date_limite_candidature' => '2026-10-20',
        ]);

        $count = count($candidatureStatuses);
        $payment = MissionPayment::create([
            'mission_id' => $mission->id,
            'producer_id' => $this->producer->id,
            'nombre_faces_retenues' => $count,
            'budget_par_face' => 100000,
            'montant_sous_total' => 100000 * $count,
            'commission_producteur' => 10000 * $count,
            'montant_total_producteur' => 110000 * $count,
            'commission_faces_total' => 10000 * $count,
            'montant_total_faces' => 90000 * $count,
            'status' => MissionPaymentStatus::Paid,
            'paid_at' => now()->subDays(5),
        ]);

        $entries = [];
        foreach ($candidatureStatuses as $status) {
            $face = Face::factory()->create();
            User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);
            $candidature = Candidature::factory()->create([
                'mission_id' => $mission->id,
                'face_id' => $face->id,
                'status' => $status,
            ]);
            $entries[] = MissionPaymentCandidature::create([
                'mission_payment_id' => $payment->id,
                'candidature_id' => $candidature->id,
                'face_id' => $face->id,
                'montant_face_recoit' => 90000,
                'escrow_status' => EscrowStatus::Locked,
                'locked_at' => now()->subDays(5),
            ]);
        }

        return [$mission, $entries];
    }

    /**
     * @param  list<MissionPaymentCandidature>  $entries
     */
    private function validate(Mission $mission, array $entries, string $status = 'present'): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->producerUser)->postJson(
            "/api/v1/producer/missions/{$mission->uuid}/validate-attendance",
            ['entries' => array_map(fn (MissionPaymentCandidature $e) => ['entry_id' => $e->id, 'status' => $status], $entries)],
        );
    }

    public function test_attendance_is_refused_before_the_shooting_day(): void
    {
        [$mission, $entries] = $this->closedPaidMission('2026-10-28');

        $this->validate($mission, $entries)
            ->assertStatus(422)
            ->assertJsonPath('errors.date_tournage.0', 'Les présences se valident à partir du jour du tournage (le 28/10/2026).');

        $entry = $entries[0]->refresh();
        $this->assertSame(EscrowStatus::Locked, $entry->escrow_status);
        $this->assertSame(AttendanceStatus::Pending, $entry->attendance_status ?? AttendanceStatus::Pending);
        $this->assertSame(MissionStatus::Closed, $mission->refresh()->status);
    }

    public function test_attendance_opens_at_midnight_business_time_on_the_shooting_day(): void
    {
        [$mission, $entries] = $this->closedPaidMission('2026-10-27');

        // 26/10 22:59 UTC = 23:59 à Porto-Novo, la veille du tournage.
        $this->travelTo(Carbon::parse('2026-10-26 22:59:00', 'UTC'));
        $this->validate($mission, $entries)->assertStatus(422)->assertJsonValidationErrors(['date_tournage']);

        // 26/10 23:00 UTC = 27/10 00:00 à Porto-Novo.
        $this->travelTo(Carbon::parse('2026-10-26 23:00:00', 'UTC'));
        $this->validate($mission, $entries)->assertOk();

        $this->assertSame(EscrowStatus::Released, $entries[0]->refresh()->escrow_status);
    }

    public function test_an_entry_of_a_face_who_did_not_confirm_is_refused(): void
    {
        [$mission, $entries] = $this->closedPaidMission('2026-10-26', [
            CandidatureStatus::Confirmed,
            CandidatureStatus::Accepted,
        ]);

        $this->validate($mission, $entries)
            ->assertStatus(422)
            ->assertJsonPath('errors.entries.0', "Une ou plusieurs Faces n'ont pas confirmé leur participation : leur présence ne peut pas être pointée.");

        $this->assertSame(EscrowStatus::Locked, $entries[0]->refresh()->escrow_status);
    }

    public function test_entries_of_rejected_or_cancelled_candidatures_are_refused(): void
    {
        foreach ([CandidatureStatus::Rejected, CandidatureStatus::Cancelled] as $status) {
            [$mission, $entries] = $this->closedPaidMission('2026-10-26', [$status]);

            $this->validate($mission, $entries)->assertStatus(422)->assertJsonValidationErrors(['entries']);
        }
    }

    public function test_in_progress_candidatures_can_be_marked(): void
    {
        [$mission, $entries] = $this->closedPaidMission('2026-10-26', [CandidatureStatus::InProgress]);

        $this->validate($mission, $entries, 'absent')->assertOk();

        $this->assertSame(AttendanceStatus::Absent, $entries[0]->refresh()->attendance_status);
    }

    public function test_the_service_refuses_too_before_the_shooting_day(): void
    {
        [$mission, $entries] = $this->closedPaidMission('2026-10-28');

        $this->expectException(ValidationException::class);

        app(MissionAttendanceService::class)->markAttendance($mission, [$entries[0]->id => 'present'], $this->producerUser);
    }

    public function test_the_service_refuses_an_unconfirmed_face(): void
    {
        [$mission, $entries] = $this->closedPaidMission('2026-10-26', [CandidatureStatus::Accepted]);

        try {
            app(MissionAttendanceService::class)->markAttendance($mission, [$entries[0]->id => 'present'], $this->producerUser);
            $this->fail('markAttendance aurait dû refuser une Face non confirmée.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('entries', $e->errors());
        }

        $this->assertSame(EscrowStatus::Locked, $entries[0]->refresh()->escrow_status);
    }

    public function test_the_form_explains_why_attendance_is_not_open_yet(): void
    {
        [$mission, $entries] = $this->closedPaidMission('2026-10-28', [
            CandidatureStatus::Confirmed,
            CandidatureStatus::Accepted,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$mission->uuid}/attendance-form")
            ->assertOk()
            ->assertJsonPath('data.mission.can_mark_attendance', false)
            ->assertJsonPath('data.mission.attendance_blocked_reason', 'Les présences se valident à partir du jour du tournage (le 28/10/2026).')
            ->assertJsonPath('data.mission.attendance_opens_at', '2026-10-27T23:00:00+00:00');

        $byId = collect($response->json('data.entries'))->keyBy('id');
        $this->assertTrue($byId[$entries[0]->id]['can_mark']);
        $this->assertNull($byId[$entries[0]->id]['blocked_reason']);
        $this->assertFalse($byId[$entries[1]->id]['can_mark']);
        $this->assertSame("Cette Face n'a pas confirmé sa participation.", $byId[$entries[1]->id]['blocked_reason']);

        $this->travelTo(Carbon::parse('2026-10-28 08:00:00', 'UTC'));
        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$mission->uuid}/attendance-form")
            ->assertJsonPath('data.mission.can_mark_attendance', true)
            ->assertJsonPath('data.mission.attendance_blocked_reason', null);
    }
}
