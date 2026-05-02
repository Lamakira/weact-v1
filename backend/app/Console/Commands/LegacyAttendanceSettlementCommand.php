<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AttendanceStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPaymentCandidature;
use App\Models\User;
use App\Services\MissionService;
use App\Support\LegacyAttendanceSettlement\DryRunCompleted;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LegacyAttendanceSettlementCommand extends Command
{
    protected $signature = 'missions:legacy-attendance-settlement
        {--pivot-date= : ISO date YYYY-MM-DD ; missions created strictly before this date follow legacy auto-release ; missions created on or after transition to PendingAttendanceValidation}
        {--dry-run : Compute and print the impact summary without writing anything}
        {--apply : Execute the migration (mutually exclusive with --dry-run)}';

    protected $description = 'One-shot legacy migration : settle Closed missions with Locked escrow according to a pivot date. Pre-pivot → MissionService::completeMission (legacy auto-release). Post-pivot → status flip to PendingAttendanceValidation (FIX-26.9).';

    public function __construct(
        private readonly MissionService $missionService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $pivotRaw = $this->option('pivot-date');
        if ($pivotRaw === null || $pivotRaw === '') {
            $this->error('Specify --pivot-date=YYYY-MM-DD.');

            return self::FAILURE;
        }

        // Date component is the first 10 chars (YYYY-MM-DD); the spec allows
        // a trailing ISO time component (e.g. "2026-05-01T00:00:00Z") which
        // is normalized away by startOfDay below. Reject everything else
        // upfront to keep relative phrases ("tomorrow", "now"), whitespace,
        // and calendar-impossible dates ("2026-02-30") from being silently
        // rolled by Carbon::parse.
        $datePart = substr($pivotRaw, 0, 10);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $datePart)) {
            $this->error(sprintf("Invalid --pivot-date: '%s'. Expected ISO date YYYY-MM-DD.", $pivotRaw));

            return self::FAILURE;
        }

        try {
            $pivotDate = Carbon::createFromFormat('!Y-m-d', $datePart);
            // Round-trip check: PHP's date parser silently rolls invalid calendar
            // dates (e.g. "2026-02-30" → "2026-03-02"). Comparing the formatted
            // back-version against the input rejects those.
            if ($pivotDate->format('Y-m-d') !== $datePart) {
                $this->error(sprintf("Invalid --pivot-date: '%s'. Expected ISO date YYYY-MM-DD.", $pivotRaw));

                return self::FAILURE;
            }
            $pivotDate = $pivotDate->startOfDay();
        } catch (\Throwable $e) {
            $this->error(sprintf("Invalid --pivot-date: '%s'. Expected ISO date YYYY-MM-DD.", $pivotRaw));

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $apply = (bool) $this->option('apply');
        if ($dryRun === $apply) {
            $this->error('Specify exactly one of --dry-run or --apply.');

            return self::FAILURE;
        }

        $mode = $apply ? 'apply' : 'dry-run';

        // Same cutoff as AutoValidateMissionAttendanceCommand:48 — guarantees that any
        // post-pivot mission this command flips to PendingAttendanceValidation is
        // immediately eligible for the next hourly auto-validate tick.
        $cutoffDate = now()->subHours(96)->toDateString();

        $missions = Mission::where('status', MissionStatus::Closed)
            ->where('date_tournage', '<=', $cutoffDate)
            ->whereHas('payment', fn ($q) => $q->where('status', MissionPaymentStatus::Paid))
            ->whereHas('payment.entries', fn ($q) => $q->where('escrow_status', EscrowStatus::Locked))
            ->orderBy('id')
            ->get();

        $this->info(sprintf(
            'Reconciling %d mission(s) in %s mode (pivot=%s).',
            $missions->count(),
            $mode,
            $pivotDate->toDateString(),
        ));

        $legacyCompleted = 0;
        $postPivotTransitioned = 0;
        $skipped = 0;
        $failed = 0;

        // Suppression flag mutation must be paired with its restoration; wrap both
        // the per-mission loop AND the surrounding state in a single try/finally
        // so the flag is restored even if any intermediate operation throws.
        $previousMailSuppression = config('weact.suppress_mission_completed_mail', false);
        config(['weact.suppress_mission_completed_mail' => $dryRun]);

        $loopCompleted = false;
        try {
            foreach ($missions as $mission) {
                $isPrePivot = $mission->created_at->lt($pivotDate);

                $outcome = $isPrePivot
                    ? $this->processLegacyMission($mission, $apply, $mode)
                    : $this->processPostPivotMission($mission, $apply, $mode);

                match ($outcome) {
                    'legacy_completed' => $legacyCompleted++,
                    'post_pivot_transitioned' => $postPivotTransitioned++,
                    'skipped' => $skipped++,
                    'failed' => $failed++,
                };
            }
            $loopCompleted = true;
        } finally {
            config(['weact.suppress_mission_completed_mail' => $previousMailSuppression]);
            // Print summary in finally so a fatal mid-loop still surfaces partial counts.
            // The "Aborted mid-loop" prefix tells the operator the counts are partial.
            $this->info(sprintf(
                '%s legacy_completed=%d, post_pivot_transitioned=%d, skipped=%d, failed=%d.',
                $loopCompleted ? 'Done.' : 'Aborted mid-loop —',
                $legacyCompleted,
                $postPivotTransitioned,
                $skipped,
                $failed,
            ));
        }

        return self::SUCCESS;
    }

    /**
     * @return 'legacy_completed'|'skipped'|'failed'
     */
    private function processLegacyMission(Mission $mission, bool $apply, string $mode): string
    {
        $orphanFaceIds = $this->findOrphanFaceIds($mission);
        if ($orphanFaceIds !== []) {
            Log::error('Legacy attendance settlement: orphan Face users detected', [
                'mission_id' => $mission->id,
                'category' => 'skipped:orphan_face_users',
                'reason' => 'orphan Face users',
                'orphan_face_ids' => $orphanFaceIds,
                'mode' => $mode,
            ]);
            $this->writeMissionLine($mission, $mode, 'skipped:orphan_face_users');
            $this->warn(sprintf(
                'Skipped mission #%d: orphan Face users (face_ids: %s). Legacy completion aborted to prevent stranded escrow.',
                $mission->id,
                implode(', ', $orphanFaceIds),
            ));

            return 'skipped';
        }

        $nonPendingLockedEntryIds = $this->findNonPendingLockedEntryIds($mission);
        if ($nonPendingLockedEntryIds !== []) {
            Log::warning('Legacy attendance settlement: non-pending locked attendance entries detected', [
                'mission_id' => $mission->id,
                'category' => 'skipped:non_pending_locked_entries',
                'reason' => 'non-pending locked attendance entries',
                'entry_ids' => $nonPendingLockedEntryIds,
                'mode' => $mode,
            ]);
            $this->writeMissionLine($mission, $mode, 'skipped:non_pending_locked_entries');
            $this->warn(sprintf(
                'Skipped mission #%d: non-pending locked attendance entries (entry_ids: %s). Manual review required before legacy settlement.',
                $mission->id,
                implode(', ', $nonPendingLockedEntryIds),
            ));

            return 'skipped';
        }

        try {
            DB::transaction(function () use ($mission, $apply): void {
                // Re-fetch under lock to avoid TOCTOU race with the still-active
                // legacy AutoReleaseMissionFundsCommand cron or with admin actions
                // mutating the mission between the outer fetch and processing.
                $locked = Mission::where('id', $mission->id)
                    ->where('status', MissionStatus::Closed)
                    ->lockForUpdate()
                    ->first();
                if ($locked === null) {
                    throw ValidationException::withMessages([
                        'mission' => 'Mission no longer Closed under lock (concurrent transition).',
                    ]);
                }

                // Re-check non-pending guard under lock — admin dispute resolution
                // could have flipped an entry between outer guard and lock. The
                // helper takes a `forUpdate` flag so the matched candidature
                // rows are themselves row-locked, not just the parent Mission.
                $nonPendingUnderLock = $this->findNonPendingLockedEntryIds($locked, forUpdate: true);
                if ($nonPendingUnderLock !== []) {
                    throw ValidationException::withMessages([
                        'mission' => sprintf(
                            'Non-pending locked entries detected under lock (entry_ids: %s).',
                            implode(',', $nonPendingUnderLock),
                        ),
                    ]);
                }

                $this->missionService->completeMission($locked);
                if (! $apply) {
                    throw new DryRunCompleted;
                }
            });
        } catch (DryRunCompleted) {
            // Expected in dry-run mode — DB::transaction rolled back.
        } catch (ValidationException $e) {
            Log::warning('Legacy attendance settlement: legacy mission skipped (validation)', [
                'mission_id' => $mission->id,
                'category' => 'skipped:validation',
                'reason' => $e->getMessage(),
                'error' => $e->getMessage(),
                'mode' => $mode,
            ]);
            $this->writeMissionLine($mission, $mode, 'skipped:validation');
            $this->warn(sprintf('Skipped mission #%d: %s', $mission->id, $e->getMessage()));

            return 'skipped';
        } catch (\Throwable $e) {
            Log::error('Legacy attendance settlement: legacy mission failed', [
                'mission_id' => $mission->id,
                'category' => 'failed:legacy_exception',
                'reason' => $e->getMessage(),
                'error' => $e->getMessage(),
                'mode' => $mode,
            ]);
            $this->writeMissionLine($mission, $mode, 'failed:legacy_exception');
            $this->error(sprintf('Failed to settle mission #%d: %s', $mission->id, $e->getMessage()));

            return 'failed';
        }

        Log::info(
            $apply
                ? 'Legacy attendance settlement: mission processed'
                : 'Legacy attendance settlement: mission previewed',
            [
                'mission_id' => $mission->id,
                'category' => 'legacy_completed',
                'mode' => $mode,
            ]
        );

        $this->writeMissionLine($mission, $mode, 'legacy_completed');

        return 'legacy_completed';
    }

    /**
     * @return 'post_pivot_transitioned'|'skipped'|'failed'
     */
    private function processPostPivotMission(Mission $mission, bool $apply, string $mode): string
    {
        try {
            DB::transaction(function () use ($mission, $apply): void {
                $updated = Mission::where('id', $mission->id)
                    ->where('status', MissionStatus::Closed)
                    ->whereHas('payment', fn ($q) => $q->where('status', MissionPaymentStatus::Paid))
                    ->whereHas('payment.entries', fn ($q) => $q->where('escrow_status', EscrowStatus::Locked))
                    ->update(['status' => MissionStatus::PendingAttendanceValidation]);

                if ($updated !== 1) {
                    throw ValidationException::withMessages([
                        'mission' => 'Mission is no longer eligible for post-pivot transition.',
                    ]);
                }

                if (! $apply) {
                    throw new DryRunCompleted;
                }
            });
        } catch (DryRunCompleted) {
            // Expected in dry-run mode.
        } catch (ValidationException $e) {
            Log::warning('Legacy attendance settlement: post-pivot mission skipped (validation)', [
                'mission_id' => $mission->id,
                'category' => 'skipped:validation',
                'reason' => $e->getMessage(),
                'error' => $e->getMessage(),
                'mode' => $mode,
            ]);
            $this->writeMissionLine($mission, $mode, 'skipped:validation');
            $this->warn(sprintf('Skipped mission #%d: %s', $mission->id, $e->getMessage()));

            return 'skipped';
        } catch (\Throwable $e) {
            Log::error('Legacy attendance settlement: post-pivot mission failed', [
                'mission_id' => $mission->id,
                'category' => 'failed:post_pivot_exception',
                'reason' => $e->getMessage(),
                'error' => $e->getMessage(),
                'mode' => $mode,
            ]);
            $this->writeMissionLine($mission, $mode, 'failed:post_pivot_exception');
            $this->error(sprintf('Failed to settle mission #%d: %s', $mission->id, $e->getMessage()));

            return 'failed';
        }

        Log::info(
            $apply
                ? 'Legacy attendance settlement: mission processed'
                : 'Legacy attendance settlement: mission previewed',
            [
                'mission_id' => $mission->id,
                'category' => 'post_pivot_transitioned',
                'mode' => $mode,
            ]
        );

        $this->writeMissionLine($mission, $mode, 'post_pivot_transitioned');

        return 'post_pivot_transitioned';
    }

    private function writeMissionLine(Mission $mission, string $mode, string $category): void
    {
        $this->line(sprintf(
            '[%s] mission_id=%d uuid=%s created_at=%s category=%s',
            $mode,
            $mission->id,
            $mission->uuid,
            $mission->created_at->toIso8601String(),
            $category,
        ));
    }

    /**
     * Strict mirror of AutoValidateMissionAttendanceCommand::findOrphanFaceIds, but
     * unscoped on attendance_status: the FIX-26.2 bridge inside MissionService::completeMission
     * auto-marks every Locked+Pending entry as Present before releaseFunds, so the orphan
     * check must cover ALL Locked entries (the attendance_status filter is enforced
     * separately by findNonPendingLockedEntryIds).
     *
     * @return list<int>
     */
    private function findOrphanFaceIds(Mission $mission): array
    {
        /** @var list<int> $expectedFaceIds */
        $expectedFaceIds = MissionPaymentCandidature::query()
            ->whereHas('missionPayment', fn ($q) => $q->where('mission_id', $mission->id))
            ->where('escrow_status', EscrowStatus::Locked)
            ->pluck('face_id')
            ->all();

        if ($expectedFaceIds === []) {
            return [];
        }

        /** @var list<int> $resolvedFaceIds */
        $resolvedFaceIds = User::query()
            ->where('userable_type', Face::class)
            ->whereIn('userable_id', $expectedFaceIds)
            ->pluck('userable_id')
            ->all();

        return array_values(array_diff($expectedFaceIds, $resolvedFaceIds));
    }

    /**
     * @return list<int>
     */
    private function findNonPendingLockedEntryIds(Mission $mission, bool $forUpdate = false): array
    {
        $query = MissionPaymentCandidature::query()
            ->whereHas('missionPayment', fn ($q) => $q->where('mission_id', $mission->id))
            ->where('escrow_status', EscrowStatus::Locked)
            ->where('attendance_status', '!=', AttendanceStatus::Pending);

        if ($forUpdate) {
            // When called from inside the per-mission DB::transaction, lock the
            // matched candidature rows so admin dispute resolution committed
            // between the outer guard and this re-check cannot slip through.
            $query->lockForUpdate();
        }

        /** @var list<int> $entryIds */
        $entryIds = $query->pluck('id')->all();

        return $entryIds;
    }
}
