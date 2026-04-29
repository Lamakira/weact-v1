<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AttendanceStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionStatus;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPaymentCandidature;
use App\Models\User;
use App\Services\MissionAttendanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AutoValidateMissionAttendanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'missions:auto-validate-attendance';

    /**
     * The console command description.
     */
    protected $description = 'Auto-validate all Locked+Pending mission entries as present once 72h have elapsed past date_tournage 23:59:59 without Producer action (FIX-26.6).';

    public function __construct(
        private readonly MissionAttendanceService $attendanceService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Product deadline: end_of_day(date_tournage) + 72h
        //   ≈ start_of_day(date_tournage) + 96h (off by 1s, immaterial at hourly cadence).
        // Since `date_tournage` is stored as DATE (no time component), the SQL filter
        // truncates `now() - 96h` to a date string and compares dates only. The cron
        // therefore fires no earlier than 96h after start_of_day(date_tournage) — i.e.
        // never before the product deadline — and may run up to ~24h late at worst.
        $cutoffDate = now()->subHours(96)->toDateString();

        $missions = Mission::where('status', MissionStatus::PendingAttendanceValidation)
            ->where('date_tournage', '<=', $cutoffDate)
            ->whereHas(
                'payment.entries',
                fn ($query) => $query
                    ->where('escrow_status', EscrowStatus::Locked)
                    ->where('attendance_status', AttendanceStatus::Pending)
            )
            ->get();

        $this->info("Found {$missions->count()} mission(s) to auto-validate.");

        $validated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($missions as $mission) {
            $orphanFaceIds = $this->findOrphanFaceIds($mission);
            if ($orphanFaceIds !== []) {
                Log::error('Auto-validate skipped: orphan Face users detected', [
                    'mission_id' => $mission->id,
                    'orphan_face_ids' => $orphanFaceIds,
                ]);
                $this->warn(
                    "Skipped mission #{$mission->id}: orphan Face users (face_ids: ".implode(', ', $orphanFaceIds).
                    '). Auto-validation aborted to prevent stranded escrow.'
                );
                $skipped++;

                continue;
            }

            try {
                $this->attendanceService->autoValidatePendingAsPresent($mission);
                $this->info("Auto-validated mission #{$mission->id} — {$mission->titre}");
                $validated++;
            } catch (ValidationException $e) {
                $this->warn("Skipped mission #{$mission->id}: {$e->getMessage()}");
                $skipped++;
            } catch (\Throwable $e) {
                $this->error("Failed to auto-validate mission #{$mission->id}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Done. Validated: {$validated}, Skipped: {$skipped}, Failed: {$failed}.");

        return self::SUCCESS;
    }

    /**
     * Defensive guard against `MissionPaymentService::releaseToFace` silent-noop on
     * orphan Face users (User row missing for a Face profile that still has live
     * `Locked + Pending` entries). Without this guard, the service would flip the
     * entry to `Locked + Present` and `tryCompleteIfReady` would complete the mission
     * with stranded escrow + no FinancialEvent.
     *
     * @return list<int>
     */
    private function findOrphanFaceIds(Mission $mission): array
    {
        /** @var list<int> $expectedFaceIds */
        $expectedFaceIds = MissionPaymentCandidature::query()
            ->whereHas('missionPayment', fn ($query) => $query->where('mission_id', $mission->id))
            ->where('escrow_status', EscrowStatus::Locked)
            ->where('attendance_status', AttendanceStatus::Pending)
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
}
