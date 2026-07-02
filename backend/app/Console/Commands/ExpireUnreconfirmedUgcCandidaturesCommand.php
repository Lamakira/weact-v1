<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CandidatureStatus;
use App\Enums\MissionType;
use App\Models\Candidature;
use App\Services\MissionPaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Sweep de reconfirmation post-acceptation d'une candidature mission UGC (ugc-9-1, D-9.1.g).
 *
 * Une candidature `accepted` (Face fantôme) qui ne reconfirme ni ne décline jamais
 * immobiliserait à vie l'escrow hybride du Producteur et bloquerait un slot de capacité.
 * Passé `ugc.reconfirm_window_hours` (48 h, ancré sur `accepted_at`), on dénoue :
 * refund de l'escrow hybride au Producteur, candidature → Cancelled, slot libéré +
 * réouverture `Closed → Published`. Couvre l'hybride (refund) ET le produit-seul (libère
 * le slot, aucun mouvement d'argent — pas d'entry escrow).
 *
 * Orthogonal à ExpireUnacceptedUgcDealsCommand (lui = pending/pré-acceptation, nous =
 * accepted/post-acceptation).
 */
class ExpireUnreconfirmedUgcCandidaturesCommand extends Command
{
    protected $signature = 'ugc:expire-unreconfirmed-candidatures';

    protected $description = 'Unwind UGC mission candidatures accepted but never reconfirmed past the reconfirmation window (refund hybrid escrow, free the slot, reopen the mission)';

    public function __construct(
        private readonly MissionPaymentService $payments,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Fallback 48 en dur : un cache config stale (clé absente) donnerait subHours(0) →
        // cutoff = now() → dénouement massif de tout l'encours accepté. Calque la défense
        // de ExpireUnacceptedUgcDealsCommand:35.
        $cutoff = now()->subHours((int) config('ugc.reconfirm_window_hours', 48));

        $candidatures = Candidature::query()
            ->where('status', CandidatureStatus::Accepted->value)
            ->whereNotNull('accepted_at') // candidatures pré-9-1 sans ancrage : ignorées
            ->where('accepted_at', '<=', $cutoff)
            ->whereHas('mission', fn ($q) => $q->where('type_mission', MissionType::Ugc->value))
            ->get();

        $this->info("Found {$candidatures->count()} unreconfirmed UGC candidature(s) past the reconfirmation window.");

        $unwound = 0;
        $failures = 0;

        foreach ($candidatures as $candidature) {
            // No-throw par candidature : une exception ne doit pas empoisonner le sweep
            // ni stopper les candidatures suivantes (cf. project_fedapay_webhook_no_throw).
            try {
                if ($this->payments->unwindUgcCandidatureSlot($candidature, 'reconfirm_window_expired')) {
                    $this->info("Unwound UGC candidature #{$candidature->id} — slot freed.");
                    $unwound++;
                }
            } catch (\Throwable $e) {
                $failures++;
                Log::critical('Sweep reconfirm-deadline échoué pour une candidature', [
                    'candidature_id' => $candidature->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. Candidatures unwound: {$unwound}.".($failures > 0 ? " Failures: {$failures}." : ''));

        // OWASP A09 (L-8): surface silent failures to the scheduler/monitoring — a batch where
        // candidatures threw must not exit SUCCESS. Per-item no-throw above is preserved.
        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
