<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Models\Mission;
use App\Services\CandidatureResponseDeadlineService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Lot B6 (D3) : expire les candidatures restées sans réponse du Producer après l'échéance
 * (clôture des candidatures + 72 h, ou début du jour de tournage), Face prévenue.
 */
class ExpireUnansweredCandidaturesCommand extends Command
{
    protected $signature = 'missions:expire-unanswered-candidatures';

    protected $description = 'Expire pending candidatures the Producer did not answer within 72 h after applications closed (never a selection being paid).';

    public function __construct(
        private readonly CandidatureResponseDeadlineService $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Sur-ensemble SQL borné (date limite passée), échéance exacte en PHP.
        $missions = Mission::query()
            ->whereNotNull('date_limite_candidature')
            ->where('date_limite_candidature', '<', Mission::businessToday())
            ->where('status', '!=', MissionStatus::PendingPayment->value)
            ->whereHas('candidatures', fn ($query) => $query->where('status', CandidatureStatus::Pending->value))
            ->get()
            ->filter(fn (Mission $mission): bool => ($deadline = $mission->producerResponseDeadline()) !== null && now()->gte($deadline));

        $expired = 0;
        $failures = 0;

        foreach ($missions as $mission) {
            try {
                $expired += $this->service->expireUnanswered($mission);
            } catch (\Throwable $e) {
                $failures++;
                Log::critical('Expiration des candidatures sans réponse échouée', [
                    'mission_id' => $mission->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Candidatures expired: {$expired}.".($failures > 0 ? " Failures: {$failures}." : ''));

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
