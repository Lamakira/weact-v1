<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AttendanceStatus;
use App\Enums\EscrowStatus;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\User;
use App\Services\MissionAttendanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SettleDisputedMissionAttendanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'missions:settle-disputed-attendance';

    /**
     * The console command description.
     */
    protected $description = 'Auto-settle Locked+Absent mission entries (refund Producer 100%) once the 72h Face dispute window expires without contestation (FIX-26.6).';

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
        $cutoff = now()->subHours(72);

        $entries = MissionPaymentCandidature::where('escrow_status', EscrowStatus::Locked)
            ->where('attendance_status', AttendanceStatus::Absent)
            ->whereNotNull('notified_at')
            ->where('notified_at', '<=', $cutoff)
            ->with(['missionPayment.mission'])
            ->get();

        $this->info("Found {$entries->count()} entry(ies) to auto-settle.");

        $settled = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($entries as $entry) {
            $missionId = $entry->missionPayment?->mission?->id;
            $missionTitre = $entry->missionPayment?->mission?->titre;
            $producerId = $entry->missionPayment?->mission?->producer_id;

            if ($producerId === null || ! $this->producerUserExists((int) $producerId)) {
                Log::error('Settle-disputed skipped: orphan Producer user detected', [
                    'entry_id' => $entry->id,
                    'mission_id' => $missionId,
                    'producer_id' => $producerId,
                ]);
                $this->warn(
                    "Skipped entry #{$entry->id} (mission #{$missionId}): orphan Producer user (producer_id: {$producerId}). Settlement aborted to prevent infinite cron retry."
                );
                $skipped++;

                continue;
            }

            try {
                $this->attendanceService->autoSettleAbsentAfterDisputeWindow($entry);
                $this->info(
                    "Auto-settled entry #{$entry->id} (mission #{$missionId} — {$missionTitre}): refunded {$entry->montant_face_recoit} XOF to producer."
                );
                $settled++;
            } catch (ValidationException $e) {
                $this->warn("Skipped entry #{$entry->id} (mission #{$missionId}): {$e->getMessage()}");
                $skipped++;
            } catch (\Throwable $e) {
                $this->error("Failed to auto-settle entry #{$entry->id} (mission #{$missionId}): {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Done. Settled: {$settled}, Skipped: {$skipped}, Failed: {$failed}.");

        return self::SUCCESS;
    }

    /**
     * Defensive guard against `MissionPaymentService::refundToProducer` silent-noop on
     * orphan Producer users (User row missing for a Producer profile that owns a
     * `Locked + Absent` entry past its 72h dispute window). Without this guard, the
     * service would no-op without flipping `escrow_status`, and the SQL filter would
     * re-match the same entry every hour forever.
     */
    private function producerUserExists(int $producerId): bool
    {
        return User::query()
            ->where('userable_type', Producer::class)
            ->where('userable_id', $producerId)
            ->exists();
    }
}
