<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Enums\UgcTunnelStatus;
use App\Models\Candidature;
use App\Models\Mission;
use App\Services\MissionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Lot B8 — fin des missions UGC, idempotent (horaire, et rattrapage des données existantes) :
 *  1. candidature produit seul dont l'Avis a été validé (tunnel completed) mais restée
 *     confirmed / in_progress (ancien comportement) → completed ;
 *  2. mission UGC dont le recrutement est fini et dont toutes les candidatures engagées
 *     sont terminales (au moins une livraison terminée) → completed (Mission::completionBlocker).
 * Aucun argent ne bouge : chaque candidature est déjà réglée.
 */
class CompleteFinishedUgcMissionsCommand extends Command
{
    protected $signature = 'missions:complete-finished-ugc';

    protected $description = 'Complete product-only UGC candidatures whose Avis was validated, then UGC missions whose engaged candidatures are all terminal';

    public function __construct(
        private readonly MissionService $missionService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $candidaturesCompleted = Candidature::query()
            ->whereIn('status', [CandidatureStatus::Confirmed->value, CandidatureStatus::InProgress->value])
            ->whereHas('mission', fn ($query) => $query->where('type_mission', MissionType::Ugc->value))
            ->whereHas('shipment', fn ($query) => $query->where('tunnel_status', UgcTunnelStatus::Completed->value))
            // Produit seul : une entry hybride encore bloquée se libère par son propre chemin.
            ->whereDoesntHave('paymentEntry')
            ->update(['status' => CandidatureStatus::Completed->value]);

        $this->info("Candidatures completed: {$candidaturesCompleted}.");

        $missions = Mission::query()
            ->where('type_mission', MissionType::Ugc->value)
            ->whereIn('status', [MissionStatus::Published->value, MissionStatus::Closed->value])
            ->whereHas('candidatures', fn ($query) => $query->where('status', CandidatureStatus::Completed->value))
            ->get();

        $completed = 0;
        $failures = 0;

        foreach ($missions as $mission) {
            try {
                if ($this->missionService->completeUgcMissionIfFinished($mission)) {
                    $this->info("Completed UGC mission #{$mission->id}");
                    $completed++;
                }
            } catch (\Throwable $e) {
                $failures++;
                Log::critical('Fin de mission UGC échouée', [
                    'mission_id' => $mission->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("UGC mission(s) completed: {$completed}.".($failures > 0 ? " Failures: {$failures}." : ''));

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
