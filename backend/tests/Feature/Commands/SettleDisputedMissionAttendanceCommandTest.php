<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

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
use App\Services\MissionAttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class SettleDisputedMissionAttendanceCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    /**
     * Local fixture helper — augments AutoValidateMissionAttendanceCommandTest::makeFixture
     * with $notifiedAtSubHours to set entry.notified_at relative to now() (FIX-26.6).
     *
     * @return array{0: Mission, 1: list<array{entry: MissionPaymentCandidature, faceUser: User}>, 2: User}
     */
    private function makeFixture(
        MissionStatus $status,
        string $dateTournage,
        int $faceCount,
        AttendanceStatus $entryAttendance = AttendanceStatus::Pending,
        ?int $notifiedAtSubHours = null,
    ): array {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $mission = Mission::factory()->closed()->create([
            'producer_id' => $producer->id,
            'date_tournage' => $dateTournage,
        ]);

        if ($status !== MissionStatus::Closed) {
            $mission->update(['status' => $status]);
            $mission->refresh();
        }

        $payment = MissionPayment::create([
            'mission_id' => $mission->id,
            'producer_id' => $producer->id,
            'nombre_faces_retenues' => $faceCount,
            'budget_par_face' => 100000,
            'montant_sous_total' => 100000 * $faceCount,
            'commission_producteur' => 10000 * $faceCount,
            'montant_total_producteur' => 110000 * $faceCount,
            'commission_faces_total' => 10000 * $faceCount,
            'montant_total_faces' => 90000 * $faceCount,
            'status' => MissionPaymentStatus::Paid,
            'paid_at' => now()->subDays(10),
        ]);

        $faces = [];
        for ($i = 0; $i < $faceCount; $i++) {
            $face = Face::factory()->create();
            $faceUser = User::factory()->create([
                'userable_type' => Face::class,
                'userable_id' => $face->id,
            ]);
            $candidature = Candidature::factory()->create([
                'mission_id' => $mission->id,
                'face_id' => $face->id,
                'status' => CandidatureStatus::Confirmed,
            ]);

            $entryAttrs = [
                'mission_payment_id' => $payment->id,
                'candidature_id' => $candidature->id,
                'face_id' => $face->id,
                'montant_face_recoit' => 90000,
                'escrow_status' => EscrowStatus::Locked,
                'attendance_status' => $entryAttendance,
                'locked_at' => now()->subDays(10),
            ];

            if ($notifiedAtSubHours !== null) {
                $entryAttrs['notified_at'] = now()->subHours($notifiedAtSubHours);
            }

            $entry = MissionPaymentCandidature::create($entryAttrs);

            $faces[] = ['entry' => $entry, 'faceUser' => $faceUser];
        }

        return [$mission, $faces, $producerUser];
    }

    public function test_command_settles_absent_entries_past_dispute_window(): void
    {
        [$mission, $faces, $producerUser] = $this->makeFixture(
            MissionStatus::PendingAttendanceValidation,
            now()->subDays(5)->toDateString(),
            1,
            AttendanceStatus::Absent,
            73,
        );

        $producerBalanceBefore = $producerUser->refresh()->balance;

        $this->artisan('missions:settle-disputed-attendance')
            ->expectsOutputToContain('Found 1 entry(ies) to auto-settle.')
            ->expectsOutputToContain(
                "Auto-settled entry #{$faces[0]['entry']->id} (mission #{$mission->id} — {$mission->titre}): refunded 90000 XOF to producer."
            )
            ->expectsOutputToContain('Done. Settled: 1, Skipped: 0, Failed: 0.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('mission_payment_candidatures', [
            'id' => $faces[0]['entry']->id,
            'escrow_status' => EscrowStatus::Refunded->value,
            'attendance_status' => AttendanceStatus::Absent->value,
        ]);

        $producerUser->refresh();
        $this->assertSame($producerBalanceBefore + 90000, $producerUser->balance);

        $this->assertDatabaseHas('financial_events', [
            'idempotency_key' => "mission_attendance_refund:{$faces[0]['entry']->id}",
        ]);

        $this->assertDatabaseHas('missions', [
            'id' => $mission->id,
            'status' => MissionStatus::Completed->value,
        ]);
    }

    public function test_command_skips_absent_entries_within_window(): void
    {
        [, $faces] = $this->makeFixture(
            MissionStatus::PendingAttendanceValidation,
            now()->subDays(5)->toDateString(),
            1,
            AttendanceStatus::Absent,
            71,
        );

        $this->artisan('missions:settle-disputed-attendance')
            ->expectsOutputToContain('Found 0 entry(ies) to auto-settle.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('mission_payment_candidatures', [
            'id' => $faces[0]['entry']->id,
            'escrow_status' => EscrowStatus::Locked->value,
            'attendance_status' => AttendanceStatus::Absent->value,
        ]);
    }

    public function test_command_does_not_touch_non_absent_entries(): void
    {
        [, $facesA] = $this->makeFixture(
            MissionStatus::PendingAttendanceValidation,
            now()->subDays(5)->toDateString(),
            1,
            AttendanceStatus::Pending,
        );

        [, $facesB] = $this->makeFixture(
            MissionStatus::PendingAttendanceValidation,
            now()->subDays(5)->toDateString(),
            1,
            AttendanceStatus::Disputed,
            73,
        );

        $this->artisan('missions:settle-disputed-attendance')
            ->expectsOutputToContain('Found 0 entry(ies) to auto-settle.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('mission_payment_candidatures', [
            'id' => $facesA[0]['entry']->id,
            'escrow_status' => EscrowStatus::Locked->value,
            'attendance_status' => AttendanceStatus::Pending->value,
        ]);
        $this->assertDatabaseHas('mission_payment_candidatures', [
            'id' => $facesB[0]['entry']->id,
            'escrow_status' => EscrowStatus::Locked->value,
            'attendance_status' => AttendanceStatus::Disputed->value,
        ]);
    }

    public function test_command_processes_multiple_eligible_entries_across_missions(): void
    {
        [$missionA] = $this->makeFixture(MissionStatus::PendingAttendanceValidation, now()->subDays(5)->toDateString(), 1, AttendanceStatus::Absent, 73);
        [$missionB] = $this->makeFixture(MissionStatus::PendingAttendanceValidation, now()->subDays(10)->toDateString(), 1, AttendanceStatus::Absent, 100);
        [$missionC] = $this->makeFixture(MissionStatus::PendingAttendanceValidation, now()->subDays(15)->toDateString(), 1, AttendanceStatus::Absent, 200);

        $this->artisan('missions:settle-disputed-attendance')
            ->expectsOutputToContain('Found 3 entry(ies) to auto-settle.')
            ->expectsOutputToContain('Done. Settled: 3, Skipped: 0, Failed: 0.')
            ->assertExitCode(0);

        foreach ([$missionA, $missionB, $missionC] as $m) {
            $this->assertDatabaseHas('missions', ['id' => $m->id, 'status' => MissionStatus::Completed->value]);
        }
    }

    public function test_command_is_idempotent_on_consecutive_runs(): void
    {
        [, $faces, $producerUser] = $this->makeFixture(
            MissionStatus::PendingAttendanceValidation,
            now()->subDays(5)->toDateString(),
            1,
            AttendanceStatus::Absent,
            73,
        );

        $this->artisan('missions:settle-disputed-attendance')->assertExitCode(0);

        $producerUser->refresh();
        $balanceAfterFirst = $producerUser->balance;

        $this->artisan('missions:settle-disputed-attendance')
            ->expectsOutputToContain('Found 0 entry(ies) to auto-settle.')
            ->assertExitCode(0);

        $producerUser->refresh();
        $this->assertSame($balanceAfterFirst, $producerUser->balance);

        $this->assertSame(
            1,
            FinancialEvent::where('idempotency_key', "mission_attendance_refund:{$faces[0]['entry']->id}")->count(),
        );
    }

    public function test_command_skips_entries_with_null_notified_at(): void
    {
        [, $faces] = $this->makeFixture(
            MissionStatus::PendingAttendanceValidation,
            now()->subDays(5)->toDateString(),
            1,
            AttendanceStatus::Absent,
            73,
        );

        $faces[0]['entry']->update(['notified_at' => null]);

        $this->artisan('missions:settle-disputed-attendance')
            ->expectsOutputToContain('Found 0 entry(ies) to auto-settle.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('mission_payment_candidatures', [
            'id' => $faces[0]['entry']->id,
            'escrow_status' => EscrowStatus::Locked->value,
        ]);
    }

    public function test_command_increments_skipped_counter_on_validation_exception(): void
    {
        [, $faces] = $this->makeFixture(
            MissionStatus::PendingAttendanceValidation,
            now()->subDays(5)->toDateString(),
            1,
            AttendanceStatus::Absent,
            73,
        );

        $mock = Mockery::mock(MissionAttendanceService::class);
        $mock->shouldReceive('autoSettleAbsentAfterDisputeWindow')
            ->once()
            ->andThrow(ValidationException::withMessages([
                'entry' => ['Race condition simulée.'],
            ]));

        $this->app->instance(MissionAttendanceService::class, $mock);

        $this->artisan('missions:settle-disputed-attendance')
            ->expectsOutputToContain('Skipped entry #'.$faces[0]['entry']->id)
            ->expectsOutputToContain('Done. Settled: 0, Skipped: 1, Failed: 0.')
            ->assertExitCode(0);
    }

    public function test_command_increments_failed_counter_on_runtime_exception(): void
    {
        [$mission, $faces] = $this->makeFixture(
            MissionStatus::PendingAttendanceValidation,
            now()->subDays(5)->toDateString(),
            1,
            AttendanceStatus::Absent,
            73,
        );

        $mock = Mockery::mock(MissionAttendanceService::class);
        $mock->shouldReceive('autoSettleAbsentAfterDisputeWindow')
            ->once()
            ->andThrow(new \RuntimeException('Bug interne simulé.'));

        $this->app->instance(MissionAttendanceService::class, $mock);

        $entryId = $faces[0]['entry']->id;

        $this->artisan('missions:settle-disputed-attendance')
            ->expectsOutputToContain("Failed to auto-settle entry #{$entryId}")
            ->expectsOutputToContain('Done. Settled: 0, Skipped: 0, Failed: 1.')
            ->assertExitCode(0);
    }

    public function test_command_skips_entry_with_orphan_producer_user_to_prevent_infinite_retry(): void
    {
        [$mission, $faces] = $this->makeFixture(
            MissionStatus::PendingAttendanceValidation,
            now()->subDays(5)->toDateString(),
            1,
            AttendanceStatus::Absent,
            73,
        );

        // Simulate orphan Producer: delete the User row that mirrors the Producer profile.
        User::where('userable_type', Producer::class)
            ->where('userable_id', $mission->producer_id)
            ->delete();

        $mock = Mockery::mock(MissionAttendanceService::class);
        $mock->shouldNotReceive('autoSettleAbsentAfterDisputeWindow');
        $this->app->instance(MissionAttendanceService::class, $mock);

        $entryId = $faces[0]['entry']->id;

        $this->artisan('missions:settle-disputed-attendance')
            ->expectsOutputToContain("Skipped entry #{$entryId} (mission #{$mission->id}): orphan Producer user")
            ->expectsOutputToContain('Done. Settled: 0, Skipped: 1, Failed: 0.')
            ->assertExitCode(0);

        // Entry untouched — still Locked+Absent, ready to be settled once the orphan is fixed.
        $this->assertDatabaseHas('mission_payment_candidatures', [
            'id' => $entryId,
            'escrow_status' => EscrowStatus::Locked->value,
            'attendance_status' => AttendanceStatus::Absent->value,
        ]);
    }
}
