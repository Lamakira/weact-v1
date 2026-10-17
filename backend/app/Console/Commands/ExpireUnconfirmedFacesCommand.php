<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CandidatureStatus;
use App\Models\Candidature;
use App\Services\MissionPaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Lot B3 — libère et rembourse la place d'une Face sélectionnée (mission cash payée) qui n'a pas confirmé
 * sa participation avant l'échéance (cf. Candidature::confirmationDeadline : 48 h après le paiement, ou le
 * début du jour de tournage s'il est plus proche). Sans cela, une Face fantôme bloque la clôture de la
 * mission à vie. Les candidatures UGC ont leur propre sweep (ugc:expire-unreconfirmed-candidatures).
 */
class ExpireUnconfirmedFacesCommand extends Command
{
    protected $signature = 'missions:expire-unconfirmed-faces';

    protected $description = 'Expire selected Faces who did not confirm before the deadline: free the place and refund the Producer wallet (budget_par_face).';

    public function __construct(
        private readonly MissionPaymentService $payments,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $candidatures = Candidature::query()
            ->where('status', CandidatureStatus::Accepted->value)
            ->whereNotNull('accepted_at') // sans ancrage : ignorées (le backfill de migration couvre l'existant)
            ->whereHas('paymentEntry', fn ($query) => $query->whereNotNull('mission_payment_id'))
            ->with('mission')
            ->get()
            ->filter(fn (Candidature $candidature): bool => ($deadline = $candidature->confirmationDeadline()) !== null
                && $deadline->lessThanOrEqualTo(now()));

        $this->info("Found {$candidatures->count()} unconfirmed Face(s) past the confirmation deadline.");

        $expired = 0;
        $failures = 0;

        foreach ($candidatures as $candidature) {
            // No-throw par candidature : une exception ne doit ni empoisonner le sweep ni stopper les suivantes.
            try {
                if ($this->payments->expireUnconfirmedFace($candidature)) {
                    $this->info("Expired candidature #{$candidature->id} — place freed and Producer refunded.");
                    $expired++;
                }
            } catch (\Throwable $e) {
                $failures++;
                Log::critical('Sweep confirmation Face échoué pour une candidature', [
                    'candidature_id' => $candidature->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. Candidatures expired: {$expired}.".($failures > 0 ? " Failures: {$failures}." : ''));

        // Un lot où des candidatures ont levé une exception ne doit pas sortir en SUCCESS (monitoring).
        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
