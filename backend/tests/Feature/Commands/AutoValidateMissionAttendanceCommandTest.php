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

class AutoValidateMissionAttendanceCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    /**
     * Local fixture helper — duplicate of MissionAttendanceServiceTest::createPaidMissionWithFaces,
     * adapted to set arbitrary date_tournage and mission status (FIX-26.6).
     *
     * @return array{0: Mission, 1: list<array{entry: MissionPaymentCandidature, faceUser: User}>, 2: User}
     */
    private function makeFixture(
        MissionStatus $status,
        string $dateTournage,
        int $faceCount,
        AttendanceStatus $entryAttendance = AttendanceStatus::Pending,
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
            $entry = MissionPaymentCandidature::create([
                'mission_payment_id' => $payment->id,
                'candidature_id' => $candidature->id,
                'face_id' => $face->id,
                'montant_face_recoit' => 90000,
                'escrow_status' => EscrowStatus::Locked,
                'attendance_status' => $entryAttendance,
                'locked_at' => now()->subDays(10),
            ]);

            $faces[] = ['entry' => $entry, 'faceUser' => $faceUser];
        }

        return [$mission, $faces, $producerUser];
    }

    public function test_command_auto_validates_pending_entries_for_eligible_mission(): void
    {
        [$mission, $faces, $producerUser] = $this->makeFixture(
            MissionStatus::PendingAttendanceValidation,
            now()->subDays(5)->toDateString(),
            2,
        );

        $this->artisan('missions:auto-validate-attendance')
            ->expectsOutputToContain('Found 1 mission(s) to auto-validate.')
            ->expectsOutputToContain("Auto-validated mission #{$mission->id}")
            ->expectsOutputToContain('Done. Validated: 1, Skipped: 0, Failed: 0.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('missions', [
            'id' => $mission->id,
            'status' => MissionStatus::Completed->value,
        ]);

        foreach ($faces as $faceData) {
            $this->assertDatabaseHas('mission_payment_candidatures', [
                'id' => $faceData['entry']->id,
                'attendance_status' => AttendanceStatus::Present->value,
                'escrow_status' => EscrowStatus::Released->value,
            ]);
            $this->assertSame(90000, $faceData['faceUser']->refresh()->balance);
        }

        $this->assertDatabaseHas('notifications', [
            'user_id' => $producerUser->id,
            'type' => 'mission_completed_producer',
        ]);
    }

    public function test_command_skips_mission_within_72h_window(): void
    {
        [$mission, $faces] = $this->makeFixture(
            MissionStatus::PendingAttendanceValidation,
            now()->subDays(2)->toDateString(),
            1,
        );

        $this->artisan('missions:auto-validate-attendance')
            ->expectsOutputToContain('Found 0 mission(s) to auto-validate.')
            ->expectsOutputToContain('Done. Validated: 0, Skipped: 0, Failed: 0.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('missions', [
            'id' => $mission->id,
            'status' => MissionStatus::PendingAttendanceValidation->value,
        ]);
        $this->assertDatabaseHas('mission_payment_candidatures', [
            'id' => $faces[0]['entry']->id,
            'attendance_status' => AttendanceStatus::Pending->value,
            'escrow_status' => EscrowStatus::Locked->value,
        ]);
    }

    public function test_command_skips_closed_status_missions(): void
    {
        [$mission] = $this->makeFixture(
            MissionStatus::Closed,
            now()->subDays(7)->toDateString(),
            1,
        );

        $this->artisan('missions:auto-validate-attendance')
            ->expectsOutputToContain('Found 0 mission(s) to auto-validate.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('missions', ['id' => $mission->id, 'status' => MissionStatus::Closed->value]);
    }

    public function test_command_skips_overdue_mission_when_no_pending_entries_remain(): void
    {
        [$mission, $faces] = $this->makeFixture(
            MissionStatus::PendingAttendanceValidation,
            now()->subDays(7)->toDateString(),
            1,
            AttendanceStatus::Absent,
        );

        $faces[0]['entry']->update(['notified_at' => now()->subHours(10)]);

        $this->artisan('missions:auto-validate-attendance')
            ->expectsOutputToContain('Found 0 mission(s) to auto-validate.')
            ->expectsOutputToContain('Done. Validated: 0, Skipped: 0, Failed: 0.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('missions', [
            'id' => $mission->id,
            'status' => MissionStatus::PendingAttendanceValidation->value,
        ]);
        $this->assertDatabaseHas('mission_payment_candidatures', [
            'id' => $faces[0]['entry']->id,
            'attendance_status' => AttendanceStatus::Absent->value,
            'escrow_status' => EscrowStatus::Locked->value,
        ]);
    }

    public function test_command_skips_completed_status_missions(): void
    {
        [$mission] = $this->makeFixture(
            MissionStatus::PendingAttendanceValidation,
            now()->subDays(7)->toDateString(),
            1,
        );
        $mission->update(['status' => MissionStatus::Completed]);

        $this->artisan('missions:auto-validate-attendance')
            ->expectsOutputToContain('Found 0 mission(s) to auto-validate.')
            ->assertExitCode(0);
    }

    public function test_command_processes_only_eligible_missions_among_mixed_set(): void
    {
        [$missionA] = $this->makeFixture(
            MissionStatus::PendingAttendanceValidation,
            now()->subDays(7)->toDateString(),
            2,
        );
        [$missionB] = $this->makeFixture(
            MissionStatus::PendingAttendanceValidation,
            now()->subDays(2)->toDateString(),
            1,
        );
        [$missionC] = $this->makeFixture(
            MissionStatus::Closed,
            now()->subDays(7)->toDateString(),
            3,
        );

        $this->artisan('missions:auto-validate-attendance')
            ->expectsOutputToContain('Found 1 mission(s) to auto-validate.')
            ->expectsOutputToContain('Done. Validated: 1, Skipped: 0, Failed: 0.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('missions', ['id' => $missionA->id, 'status' => MissionStatus::Completed->value]);
        $this->assertDatabaseHas('missions', ['id' => $missionB->id, 'status' => MissionStatus::PendingAttendanceValidation->value]);
        $this->assertDatabaseHas('missions', ['id' => $missionC->id, 'status' => MissionStatus::Closed->value]);
    }

    public function test_command_is_idempotent_on_consecutive_runs(): void
    {
        [, $faces] = $this->makeFixture(
            MissionStatus::PendingAttendanceValidation,
            now()->subDays(7)->toDateString(),
            2,
        );

        $this->artisan('missions:auto-validate-attendance')->assertExitCode(0);

        foreach ($faces as $faceData) {
            $this->assertSame(90000, $faceData['faceUser']->refresh()->balance);
        }

        $this->assertSame(2, \App\Models\WalletTransaction::count());

        $this->artisan('missions:auto-validate-attendance')
            ->expectsOutputToContain('Found 0 mission(s) to auto-validate.')
            ->assertExitCode(0);

        foreach ($faces as $faceData) {
            $this->assertSame(90000, $faceData['faceUser']->refresh()->balance);
        }

        $this->assertSame(2, \App\Models\WalletTransaction::count());

        foreach ($faces as $faceData) {
            $this->assertSame(
                1,
                FinancialEvent::where('idempotency_key', "mission_attendance_escrow_release:{$faceData['entry']->id}")->count(),
            );
        }
    }

    public function test_command_increments_skipped_counter_on_validation_exception(): void
    {
        [$mission] = $this->makeFixture(
            MissionStatus::PendingAttendanceValidation,
            now()->subDays(7)->toDateString(),
            1,
        );

        $mock = Mockery::mock(MissionAttendanceService::class);
        $mock->shouldReceive('autoValidatePendingAsPresent')
            ->once()
            ->andThrow(ValidationException::withMessages([
                'mission' => ['Race condition simulée.'],
            ]));

        $this->app->instance(MissionAttendanceService::class, $mock);

        $this->artisan('missions:auto-validate-attendance')
            ->expectsOutputToContain('Skipped mission #'.$mission->id)
            ->expectsOutputToContain('Done. Validated: 0, Skipped: 1, Failed: 0.')
            ->assertExitCode(0);
    }

    public function test_command_increments_failed_counter_on_runtime_exception(): void
    {
        [$mission] = $this->makeFixture(
            MissionStatus::PendingAttendanceValidation,
            now()->subDays(7)->toDateString(),
            1,
        );

        $mock = Mockery::mock(MissionAttendanceService::class);
        $mock->shouldReceive('autoValidatePendingAsPresent')
            ->once()
            ->andThrow(new \RuntimeException('Bug interne simulé.'));

        $this->app->instance(MissionAttendanceService::class, $mock);

        $this->artisan('missions:auto-validate-attendance')
            ->expectsOutputToContain('Failed to auto-validate mission #'.$mission->id)
            ->expectsOutputToContain('Done. Validated: 0, Skipped: 0, Failed: 1.')
            ->assertExitCode(0);
    }

    public function test_command_skips_mission_with_orphan_face_user_to_prevent_stranded_escrow(): void
    {
        [$mission, $faces] = $this->makeFixture(
            MissionStatus::PendingAttendanceValidation,
            now()->subDays(7)->toDateString(),
            1,
        );

        // Simulate orphan Face: delete the User row that mirrors the Face profile.
        User::where('userable_type', Face::class)
            ->where('userable_id', $faces[0]['entry']->face_id)
            ->delete();

        $mock = Mockery::mock(MissionAttendanceService::class);
        $mock->shouldNotReceive('autoValidatePendingAsPresent');
        $this->app->instance(MissionAttendanceService::class, $mock);

        $this->artisan('missions:auto-validate-attendance')
            ->expectsOutputToContain("Skipped mission #{$mission->id}: orphan Face users")
            ->expectsOutputToContain('Done. Validated: 0, Skipped: 1, Failed: 0.')
            ->assertExitCode(0);

        // Entry untouched — no stranded Locked+Present.
        $this->assertDatabaseHas('mission_payment_candidatures', [
            'id' => $faces[0]['entry']->id,
            'attendance_status' => AttendanceStatus::Pending->value,
            'escrow_status' => EscrowStatus::Locked->value,
        ]);

        // Mission untouched — no premature Completed flip.
        $this->assertDatabaseHas('missions', [
            'id' => $mission->id,
            'status' => MissionStatus::PendingAttendanceValidation->value,
        ]);
    }
}
