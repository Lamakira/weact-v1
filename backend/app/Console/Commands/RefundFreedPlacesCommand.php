<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\EscrowStatus;
use App\Models\MissionPaymentCandidature;
use App\Services\FreedPlaceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Lot B4 (D1) : une place libérée restée sans remplaçante au début du jour de tournage est
 * remboursée au wallet du Producer (budget_par_face ; WeAct garde ses 10 % Producer).
 */
class RefundFreedPlacesCommand extends Command
{
    protected $signature = 'missions:refund-freed-places';

    protected $description = 'Refund to the Producer wallet (budget_par_face) every freed mission place still without a replacement once the shooting day has started.';

    public function __construct(
        private readonly FreedPlaceService $freedPlaces,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $entries = MissionPaymentCandidature::query()
            ->whereNotNull('freed_at')
            ->whereNotNull('mission_payment_id')
            ->where('escrow_status', EscrowStatus::Locked->value)
            ->with('missionPayment.mission')
            ->get()
            ->filter(function (MissionPaymentCandidature $entry): bool {
                $start = $entry->missionPayment?->mission?->shootingDayStartsAt();

                return $start === null || now()->gte($start);
            });

        $refunded = 0;
        $failures = 0;

        foreach ($entries as $entry) {
            try {
                if ($this->freedPlaces->refundIfShootingDayStarted($entry)) {
                    $refunded++;
                }
            } catch (\Throwable $e) {
                $failures++;
                Log::critical('Remboursement de place libérée échoué', [
                    'entry_id' => $entry->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Freed places refunded: {$refunded}.".($failures > 0 ? " Failures: {$failures}." : ''));

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
