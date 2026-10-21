<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Mission;
use App\Services\CandidatureDigestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Lot D5 : e-mail groupé horaire au Producer pour les nouvelles candidatures d'une mission
 * (au plus un par mission et par heure). La notification in-app par candidature reste inchangée.
 */
class SendCandidatureDigestCommand extends Command
{
    protected $signature = 'missions:send-candidature-digest';

    protected $description = 'Send each Producer a grouped e-mail of the new pending candidatures since the last digest (max one per mission per hour).';

    public function __construct(
        private readonly CandidatureDigestService $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sent = 0;

        // Pagination par identifiant : la boucle modifie la colonne filtrée (candidature_digest_sent_at).
        $this->service->eligibleMissions()->lazyById(200)->each(function (Mission $mission) use (&$sent): void {
            try {
                if ($this->service->sendDigest($mission)) {
                    $sent++;
                }
            } catch (\Throwable $e) {
                Log::warning('Candidature digest failed', [
                    'mission_id' => $mission->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        $this->info("Digests sent: {$sent}.");

        return self::SUCCESS;
    }
}
