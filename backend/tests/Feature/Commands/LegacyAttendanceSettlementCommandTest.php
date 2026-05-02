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
use App\Models\WalletTransaction;
use App\Services\MissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Mockery;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;

class LegacyAttendanceSettlementCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    /**
     * Strict mirror of AutoValidateMissionAttendanceCommandTest::makeFixture, augmented
     * with an optional 5th parameter to override created_at (FIX-26.9 pre/post pivot
     * classification depends on mission.created_at).
     *
     * @return array{0: Mission, 1: list<array{entry: MissionPaymentCandidature, faceUser: User}>, 2: User}
     */
    private function makeFixture(
        MissionStatus $status,
        string $dateTournage,
        int $faceCount,
        AttendanceStatus $entryAttendance = AttendanceStatus::Pending,
        ?Carbon $createdAt = null,
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

        if ($createdAt !== null) {
            $mission->forceFill(['created_at' => $createdAt])->saveQuietly();
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

    /**
     * @return array{0: Mission, 1: Mission}
     */
    private function makeIneligibleFixtures(string $dateTournage, Carbon $createdAt): array
    {
        [$closedWithoutLockedMission, $closedFaces] = $this->makeFixture(
            MissionStatus::Closed,
            $dateTournage,
            1,
            AttendanceStatus::Present,
            $createdAt,
        );
        $closedFaces[0]['entry']->update([
            'escrow_status' => EscrowStatus::Released,
            'released_at' => now()->subDays(5),
        ]);

        [$completedMission, $completedFaces] = $this->makeFixture(
            MissionStatus::Closed,
            $dateTournage,
            1,
            AttendanceStatus::Present,
            $createdAt,
        );
        $completedFaces[0]['entry']->update([
            'escrow_status' => EscrowStatus::Released,
            'released_at' => now()->subDays(5),
        ]);
        $completedMission->update(['status' => MissionStatus::Completed]);

        return [$closedWithoutLockedMission, $completedMission];
    }

    public function test_dry_run_classifies_missions_without_mutating(): void
    {
        $pivotDate = now()->subWeek()->toDateString();

        // 3 pre-pivot missions
        $preFixtures = [];
        for ($i = 0; $i < 3; $i++) {
            $preFixtures[] = $this->makeFixture(
                MissionStatus::Closed,
                now()->subDays(20)->toDateString(),
                1,
                AttendanceStatus::Pending,
                now()->subMonth(),
            );
        }

        // 3 post-pivot missions
        $postFixtures = [];
        for ($i = 0; $i < 3; $i++) {
            $postFixtures[] = $this->makeFixture(
                MissionStatus::Closed,
                now()->subDays(5)->toDateString(),
                1,
                AttendanceStatus::Pending,
                now()->subDays(3),
            );
        }

        [$closedWithoutLockedMission, $completedMission] = $this->makeIneligibleFixtures(
            now()->subDays(20)->toDateString(),
            now()->subMonth(),
        );

        $walletTxBefore = WalletTransaction::count();
        $financialEventsBefore = FinancialEvent::count();

        $balancesBefore = [];
        foreach (array_merge($preFixtures, $postFixtures) as [$mission, $faces, $producerUser]) {
            foreach ($faces as $faceData) {
                $balancesBefore[$faceData['faceUser']->id] = $faceData['faceUser']->refresh()->balance;
            }
        }

        $this->artisan('missions:legacy-attendance-settlement', [
            '--pivot-date' => $pivotDate,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Reconciling 6 mission(s) in dry-run mode')
            ->expectsOutputToContain('category=legacy_completed')
            ->expectsOutputToContain('category=post_pivot_transitioned')
            ->expectsOutputToContain('legacy_completed=3, post_pivot_transitioned=3, skipped=0, failed=0')
            ->assertExitCode(Command::SUCCESS);

        // No DB mutation
        $this->assertSame($walletTxBefore, WalletTransaction::count());
        $this->assertSame($financialEventsBefore, FinancialEvent::count());
        Mail::assertNothingQueued();

        foreach (array_merge($preFixtures, $postFixtures) as [$mission, $faces, $producerUser]) {
            $this->assertDatabaseHas('missions', [
                'id' => $mission->id,
                'status' => MissionStatus::Closed->value,
            ]);
            foreach ($faces as $faceData) {
                $this->assertDatabaseHas('mission_payment_candidatures', [
                    'id' => $faceData['entry']->id,
                    'escrow_status' => EscrowStatus::Locked->value,
                    'attendance_status' => AttendanceStatus::Pending->value,
                ]);
                $this->assertSame(
                    $balancesBefore[$faceData['faceUser']->id],
                    $faceData['faceUser']->refresh()->balance,
                );
            }
        }
        $this->assertDatabaseHas('missions', [
            'id' => $closedWithoutLockedMission->id,
            'status' => MissionStatus::Closed->value,
        ]);
        $this->assertDatabaseHas('missions', [
            'id' => $completedMission->id,
            'status' => MissionStatus::Completed->value,
        ]);
    }

    public function test_apply_settles_pre_pivot_legacy_and_transitions_post_pivot(): void
    {
        $pivotDate = now()->subWeek()->toDateString();

        $preFixtures = [];
        for ($i = 0; $i < 3; $i++) {
            $preFixtures[] = $this->makeFixture(
                MissionStatus::Closed,
                now()->subDays(20)->toDateString(),
                1,
                AttendanceStatus::Pending,
                now()->subMonth(),
            );
        }

        $postFixtures = [];
        for ($i = 0; $i < 3; $i++) {
            $postFixtures[] = $this->makeFixture(
                MissionStatus::Closed,
                now()->subDays(5)->toDateString(),
                1,
                AttendanceStatus::Pending,
                now()->subDays(3),
            );
        }

        [$closedWithoutLockedMission, $completedMission] = $this->makeIneligibleFixtures(
            now()->subDays(20)->toDateString(),
            now()->subMonth(),
        );

        $this->artisan('missions:legacy-attendance-settlement', [
            '--pivot-date' => $pivotDate,
            '--apply' => true,
        ])
            ->expectsOutputToContain('Reconciling 6 mission(s) in apply mode')
            ->expectsOutputToContain('category=legacy_completed')
            ->expectsOutputToContain('category=post_pivot_transitioned')
            ->expectsOutputToContain('legacy_completed=3, post_pivot_transitioned=3, skipped=0, failed=0')
            ->assertExitCode(Command::SUCCESS);

        // Pre-pivot missions: completed + Face credited + notifications
        foreach ($preFixtures as [$mission, $faces, $producerUser]) {
            $this->assertDatabaseHas('missions', [
                'id' => $mission->id,
                'status' => MissionStatus::Completed->value,
            ]);
            foreach ($faces as $faceData) {
                $this->assertDatabaseHas('mission_payment_candidatures', [
                    'id' => $faceData['entry']->id,
                    'escrow_status' => EscrowStatus::Released->value,
                    'attendance_status' => AttendanceStatus::Present->value,
                ]);
                $this->assertSame(90000, $faceData['faceUser']->refresh()->balance);
                $this->assertDatabaseHas('notifications', [
                    'user_id' => $faceData['faceUser']->id,
                    'type' => 'mission_completed',
                ]);
            }
            $this->assertDatabaseHas('notifications', [
                'user_id' => $producerUser->id,
                'type' => 'mission_completed_producer',
            ]);
        }

        // Post-pivot missions: status flipped, entries untouched
        foreach ($postFixtures as [$mission, $faces, $producerUser]) {
            $this->assertDatabaseHas('missions', [
                'id' => $mission->id,
                'status' => MissionStatus::PendingAttendanceValidation->value,
            ]);
            foreach ($faces as $faceData) {
                $this->assertDatabaseHas('mission_payment_candidatures', [
                    'id' => $faceData['entry']->id,
                    'escrow_status' => EscrowStatus::Locked->value,
                    'attendance_status' => AttendanceStatus::Pending->value,
                ]);
                $this->assertSame(0, $faceData['faceUser']->refresh()->balance);
            }
        }
        $this->assertDatabaseHas('missions', [
            'id' => $closedWithoutLockedMission->id,
            'status' => MissionStatus::Closed->value,
        ]);
        $this->assertDatabaseHas('missions', [
            'id' => $completedMission->id,
            'status' => MissionStatus::Completed->value,
        ]);
        Mail::assertQueuedCount(3);
    }

    public function test_apply_skips_ineligible_missions(): void
    {
        $pivotDate = now()->subWeek()->toDateString();

        // Scenario A: Closed mission with Paid payment but entry already Released
        [$missionA, $facesA] = $this->makeFixture(
            MissionStatus::Closed,
            now()->subDays(20)->toDateString(),
            1,
            AttendanceStatus::Present,
            now()->subMonth(),
        );
        $facesA[0]['entry']->update([
            'escrow_status' => EscrowStatus::Released,
            'released_at' => now()->subDays(5),
        ]);

        // Scenario B: Completed mission with Released entry
        [$missionB, $facesB] = $this->makeFixture(
            MissionStatus::Closed,
            now()->subDays(20)->toDateString(),
            1,
            AttendanceStatus::Present,
            now()->subMonth(),
        );
        $facesB[0]['entry']->update([
            'escrow_status' => EscrowStatus::Released,
            'released_at' => now()->subDays(5),
        ]);
        $missionB->update(['status' => MissionStatus::Completed]);

        $this->artisan('missions:legacy-attendance-settlement', [
            '--pivot-date' => $pivotDate,
            '--apply' => true,
        ])
            ->expectsOutputToContain('Reconciling 0 mission(s)')
            ->expectsOutputToContain('legacy_completed=0, post_pivot_transitioned=0, skipped=0, failed=0')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_apply_is_idempotent_on_consecutive_runs(): void
    {
        $pivotDate = now()->subWeek()->toDateString();

        $preFixtures = [];
        for ($i = 0; $i < 3; $i++) {
            $preFixtures[] = $this->makeFixture(
                MissionStatus::Closed,
                now()->subDays(20)->toDateString(),
                1,
                AttendanceStatus::Pending,
                now()->subMonth(),
            );
        }

        $postFixtures = [];
        for ($i = 0; $i < 3; $i++) {
            $postFixtures[] = $this->makeFixture(
                MissionStatus::Closed,
                now()->subDays(5)->toDateString(),
                1,
                AttendanceStatus::Pending,
                now()->subDays(3),
            );
        }

        $this->artisan('missions:legacy-attendance-settlement', [
            '--pivot-date' => $pivotDate,
            '--apply' => true,
        ])->assertExitCode(Command::SUCCESS);

        $walletTxAfterRun1 = WalletTransaction::count();
        $financialEventsAfterRun1 = FinancialEvent::where('idempotency_key', 'LIKE', 'mission_attendance_escrow_release:%')->count();

        $balancesAfterRun1 = [];
        foreach (array_merge($preFixtures, $postFixtures) as [$mission, $faces, $producerUser]) {
            foreach ($faces as $faceData) {
                $balancesAfterRun1[$faceData['faceUser']->id] = $faceData['faceUser']->refresh()->balance;
            }
        }

        $this->assertSame(3, $walletTxAfterRun1, 'Run 1 should credit exactly 3 Face wallets (one per pre-pivot mission).');
        $this->assertSame(3, $financialEventsAfterRun1);

        // Second run is a no-op (no Closed missions left in scope)
        $this->artisan('missions:legacy-attendance-settlement', [
            '--pivot-date' => $pivotDate,
            '--apply' => true,
        ])
            ->expectsOutputToContain('Reconciling 0 mission(s)')
            ->expectsOutputToContain('legacy_completed=0, post_pivot_transitioned=0, skipped=0, failed=0')
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame($walletTxAfterRun1, WalletTransaction::count(), 'Run 2 must not double-credit any Face wallet.');
        $this->assertSame(
            $financialEventsAfterRun1,
            FinancialEvent::where('idempotency_key', 'LIKE', 'mission_attendance_escrow_release:%')->count(),
            'Run 2 must not duplicate any FinancialEvent.',
        );

        // Idempotency key uniqueness: each pre-pivot entry has exactly 1 EscrowRelease event.
        foreach ($preFixtures as [$mission, $faces, $producerUser]) {
            foreach ($faces as $faceData) {
                $this->assertSame(
                    1,
                    FinancialEvent::where('idempotency_key', "mission_attendance_escrow_release:{$faceData['entry']->id}")->count(),
                );
            }
        }

        foreach (array_merge($preFixtures, $postFixtures) as [$mission, $faces, $producerUser]) {
            foreach ($faces as $faceData) {
                $this->assertSame(
                    $balancesAfterRun1[$faceData['faceUser']->id],
                    $faceData['faceUser']->refresh()->balance,
                    'Face balance must remain identical after idempotent re-run.',
                );
            }
        }
    }

    public function test_apply_skips_pre_pivot_with_orphan_face_users(): void
    {
        $pivotDate = now()->subWeek()->toDateString();

        [$mission, $faces] = $this->makeFixture(
            MissionStatus::Closed,
            now()->subDays(20)->toDateString(),
            1,
            AttendanceStatus::Pending,
            now()->subMonth(),
        );

        // Simulate orphan Face by deleting the User row.
        User::where('userable_type', Face::class)
            ->where('userable_id', $faces[0]['entry']->face_id)
            ->delete();

        $this->artisan('missions:legacy-attendance-settlement', [
            '--pivot-date' => $pivotDate,
            '--apply' => true,
        ])
            ->expectsOutputToContain('category=skipped:orphan_face_users')
            ->expectsOutputToContain('Skipped mission #'.$mission->id.': orphan Face users')
            ->expectsOutputToContain('legacy_completed=0, post_pivot_transitioned=0, skipped=1, failed=0')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseHas('missions', [
            'id' => $mission->id,
            'status' => MissionStatus::Closed->value,
        ]);
        $this->assertDatabaseHas('mission_payment_candidatures', [
            'id' => $faces[0]['entry']->id,
            'escrow_status' => EscrowStatus::Locked->value,
            'attendance_status' => AttendanceStatus::Pending->value,
        ]);
    }

    public function test_apply_skips_pre_pivot_with_non_pending_locked_entries(): void
    {
        $pivotDate = now()->subWeek()->toDateString();

        [$mission, $faces] = $this->makeFixture(
            MissionStatus::Closed,
            now()->subDays(20)->toDateString(),
            1,
            AttendanceStatus::Pending,
            now()->subMonth(),
        );

        $faces[0]['entry']->update(['attendance_status' => AttendanceStatus::Disputed]);

        $this->artisan('missions:legacy-attendance-settlement', [
            '--pivot-date' => $pivotDate,
            '--apply' => true,
        ])
            ->expectsOutputToContain('category=skipped:non_pending_locked_entries')
            ->expectsOutputToContain('Skipped mission #'.$mission->id.': non-pending locked attendance entries')
            ->expectsOutputToContain('legacy_completed=0, post_pivot_transitioned=0, skipped=1, failed=0')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseHas('missions', [
            'id' => $mission->id,
            'status' => MissionStatus::Closed->value,
        ]);
        $this->assertDatabaseHas('mission_payment_candidatures', [
            'id' => $faces[0]['entry']->id,
            'escrow_status' => EscrowStatus::Locked->value,
            'attendance_status' => AttendanceStatus::Disputed->value,
        ]);
    }

    public function test_command_fails_when_pivot_date_missing(): void
    {
        $this->artisan('missions:legacy-attendance-settlement', [
            '--apply' => true,
        ])
            ->expectsOutputToContain('Specify --pivot-date=YYYY-MM-DD.')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_command_fails_when_pivot_date_invalid(): void
    {
        $this->artisan('missions:legacy-attendance-settlement', [
            '--pivot-date' => 'not-a-date',
            '--apply' => true,
        ])
            ->expectsOutputToContain("Invalid --pivot-date: 'not-a-date'.")
            ->assertExitCode(Command::FAILURE);
    }

    public function test_command_fails_when_pivot_date_is_relative_phrase(): void
    {
        $this->artisan('missions:legacy-attendance-settlement', [
            '--pivot-date' => 'tomorrow',
            '--apply' => true,
        ])
            ->expectsOutputToContain("Invalid --pivot-date: 'tomorrow'.")
            ->assertExitCode(Command::FAILURE);
    }

    public function test_command_fails_when_pivot_date_is_impossible_calendar_date(): void
    {
        $this->artisan('missions:legacy-attendance-settlement', [
            '--pivot-date' => '2026-02-30',
            '--apply' => true,
        ])
            ->expectsOutputToContain("Invalid --pivot-date: '2026-02-30'.")
            ->assertExitCode(Command::FAILURE);
    }

    public function test_command_accepts_pivot_date_with_iso_time_component(): void
    {
        // Spec line 57: parse accepte aussi '2026-05-01T00:00:00Z' — robuste si
        // l'opérateur copie-colle un timestamp ISO complet ; on strip le composant
        // time après parse via startOfDay().
        $this->artisan('missions:legacy-attendance-settlement', [
            '--pivot-date' => now()->subWeek()->toDateString().'T00:00:00Z',
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Reconciling 0 mission(s) in dry-run mode')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_command_fails_when_neither_dry_run_nor_apply(): void
    {
        $this->artisan('missions:legacy-attendance-settlement', [
            '--pivot-date' => now()->toDateString(),
        ])
            ->expectsOutputToContain('Specify exactly one of --dry-run or --apply.')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_command_fails_when_both_dry_run_and_apply(): void
    {
        $this->artisan('missions:legacy-attendance-settlement', [
            '--pivot-date' => now()->toDateString(),
            '--dry-run' => true,
            '--apply' => true,
        ])
            ->expectsOutputToContain('Specify exactly one of --dry-run or --apply.')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_apply_handles_pivot_boundary_correctly(): void
    {
        $pivotDate = '2026-05-01';

        // Mission A: created strictly before pivot midnight → pre-pivot (legacy completion).
        [$missionA, $facesA] = $this->makeFixture(
            MissionStatus::Closed,
            now()->subDays(5)->toDateString(),
            1,
            AttendanceStatus::Pending,
            Carbon::parse('2026-04-30 23:59:59'),
        );

        // Mission B: created exactly at pivot midnight → post-pivot (status flip).
        [$missionB, $facesB] = $this->makeFixture(
            MissionStatus::Closed,
            now()->subDays(5)->toDateString(),
            1,
            AttendanceStatus::Pending,
            Carbon::parse('2026-05-01 00:00:00'),
        );

        $this->artisan('missions:legacy-attendance-settlement', [
            '--pivot-date' => $pivotDate,
            '--apply' => true,
        ])
            ->expectsOutputToContain('Reconciling 2 mission(s) in apply mode')
            ->expectsOutputToContain('legacy_completed=1, post_pivot_transitioned=1, skipped=0, failed=0')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseHas('missions', [
            'id' => $missionA->id,
            'status' => MissionStatus::Completed->value,
        ]);
        $this->assertDatabaseHas('missions', [
            'id' => $missionB->id,
            'status' => MissionStatus::PendingAttendanceValidation->value,
        ]);
    }

    public function test_apply_counts_failed_when_mission_service_throws(): void
    {
        // Force MissionService::completeMission to throw on the legacy path so
        // that the \Throwable catch increments `failed` and emits the
        // structured "Failed to settle mission #X" line. Without this fixture
        // the failed=N branch is unexercised.
        $pivotDate = now()->subWeek()->toDateString();
        [$mission] = $this->makeFixture(
            MissionStatus::Closed,
            now()->subDays(20)->toDateString(),
            1,
            AttendanceStatus::Pending,
            now()->subMonth(),
        );

        $mock = Mockery::mock(MissionService::class);
        $mock->shouldReceive('completeMission')
            ->once()
            ->andThrow(new RuntimeException('Simulated upstream failure'));
        $this->app->instance(MissionService::class, $mock);

        $this->artisan('missions:legacy-attendance-settlement', [
            '--pivot-date' => $pivotDate,
            '--apply' => true,
        ])
            ->expectsOutputToContain('Failed to settle mission #'.$mission->id.': Simulated upstream failure')
            ->expectsOutputToContain('category=failed:legacy_exception')
            ->expectsOutputToContain('legacy_completed=0, post_pivot_transitioned=0, skipped=0, failed=1')
            ->assertExitCode(Command::SUCCESS);

        // Mission stays Closed because the transaction rolled back on the throw.
        $this->assertDatabaseHas('missions', [
            'id' => $mission->id,
            'status' => MissionStatus::Closed->value,
        ]);
    }

    public function test_cutoff_excludes_missions_with_date_tournage_too_recent(): void
    {
        // Pin the wall clock at noon so subHours(96)/subDays(3) always cross
        // the same date boundaries — both the fixture and the command's
        // internal `now()->subHours(96)->toDateString()` resolve to stable
        // calendar dates regardless of when the test actually runs.
        $this->travelTo(Carbon::parse('2026-05-15 12:00:00'));

        // Cutoff is now()->subHours(96)->toDateString() — a date with no time
        // component. A mission whose date_tournage is "today - 3 days" sits
        // strictly after that cutoff date and must be excluded from the scope.
        $pivotDate = now()->subWeek()->toDateString();
        [$mission] = $this->makeFixture(
            MissionStatus::Closed,
            now()->subDays(3)->toDateString(),
            1,
            AttendanceStatus::Pending,
            now()->subMonth(),
        );

        $this->artisan('missions:legacy-attendance-settlement', [
            '--pivot-date' => $pivotDate,
            '--apply' => true,
        ])
            ->expectsOutputToContain('Reconciling 0 mission(s) in apply mode')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseHas('missions', [
            'id' => $mission->id,
            'status' => MissionStatus::Closed->value,
        ]);
    }

    public function test_cutoff_includes_missions_at_or_past_threshold(): void
    {
        $this->travelTo(Carbon::parse('2026-05-15 12:00:00'));

        // A mission whose date_tournage equals the cutoff date (today - 4 days)
        // is included by the `<=` comparison. Verifies the boundary is inclusive.
        $pivotDate = now()->subWeek()->toDateString();
        [$mission] = $this->makeFixture(
            MissionStatus::Closed,
            now()->subDays(4)->toDateString(),
            1,
            AttendanceStatus::Pending,
            now()->subMonth(),
        );

        $this->artisan('missions:legacy-attendance-settlement', [
            '--pivot-date' => $pivotDate,
            '--apply' => true,
        ])
            ->expectsOutputToContain('Reconciling 1 mission(s) in apply mode')
            ->expectsOutputToContain('legacy_completed=1, post_pivot_transitioned=0, skipped=0, failed=0')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseHas('missions', [
            'id' => $mission->id,
            'status' => MissionStatus::Completed->value,
        ]);
    }
}
