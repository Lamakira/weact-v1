<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Models\Mission;
use App\Services\MissionService;
use Illuminate\Console\Command;

class AutoReleaseMissionFundsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'missions:auto-release-funds';

    /**
     * The console command description.
     */
    protected $description = 'Auto-complete missions and release escrow funds 14 days after date_tournage if still closed';

    public function __construct(
        private readonly MissionService $missionService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoff = now()->subDays(14);

        $missions = Mission::where('status', MissionStatus::Closed)
            ->where('date_tournage', '<=', $cutoff)
            ->whereHas('payment', function ($query): void {
                $query->where('status', MissionPaymentStatus::Paid);
            })
            ->with('payment.entries')
            ->get();

        $this->info("Found {$missions->count()} mission(s) to auto-complete.");

        $completed = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($missions as $mission) {
            // Lot B4 : toutes les places remboursées (aucune Face n'a jamais confirmé) : la mission reste
            // clôturée, « Terminer » est refusé au Producer et la clôture automatique ne la termine pas non plus.
            if (($mission->completionBlocker()[1] ?? null) === Mission::NO_FACE_CONFIRMED_MESSAGE) {
                $this->info("Skipped mission #{$mission->id} — all places refunded, no Face confirmed.");
                $skipped++;

                continue;
            }

            try {
                $this->missionService->completeMission($mission);
                $this->info("Auto-completed mission #{$mission->id} — {$mission->titre}");
                $completed++;
            } catch (\Throwable $e) {
                $this->error("Failed to auto-complete mission #{$mission->id}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Done. Completed: {$completed}, Failed: {$failed}, Skipped: {$skipped}.");

        return self::SUCCESS;
    }
}
