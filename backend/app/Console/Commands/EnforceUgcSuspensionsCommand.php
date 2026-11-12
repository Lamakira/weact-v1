<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\UgcSuspension;
use App\Services\Ugc\UgcSuspensionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Applique la durée d'une suspension UGC (`ugc.suspension_days`) :
 * (a) lève toute suspension active vieille d'au moins N jours (reactivate gère la clôture du
 * deal et l'event FaceUgcReactivated) ;
 * (b) retire les candidatures UGC `pending` des Faces encore suspendues (rattrape une suspension
 * antérieure au retrait automatique et toute candidature créée en course).
 * Idempotente. No-throw par suspension.
 */
class EnforceUgcSuspensionsCommand extends Command
{
    protected $signature = 'ugc:enforce-suspensions';

    protected $description = 'Lift UGC suspensions older than the configured duration and withdraw pending UGC candidatures of still-suspended Faces';

    public function __construct(
        private readonly UgcSuspensionService $suspensions,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Fallback 7 en dur : une clé absente (cache config stale) donnerait subDays(0) → levée massive.
        $cutoff = now()->subDays((int) config('ugc.suspension_days', 7));
        $failures = 0;
        $lifted = 0;
        $withdrawn = 0;

        $expired = UgcSuspension::query()
            ->whereNull('reactivated_at')
            ->where('suspended_at', '<=', $cutoff)
            ->get();

        foreach ($expired as $suspension) {
            try {
                $this->suspensions->reactivate($suspension);
                $lifted++;
            } catch (\Throwable $e) {
                $failures++;
                Log::critical('Levée automatique de suspension UGC échouée', [
                    'suspension_id' => $suspension->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $faceIds = UgcSuspension::query()
            ->whereNull('reactivated_at')
            ->pluck('face_id')
            ->unique();

        foreach ($faceIds as $faceId) {
            try {
                $withdrawn += $this->suspensions->withdrawPendingCandidatures((int) $faceId);
            } catch (\Throwable $e) {
                $failures++;
                Log::critical('Retrait des candidatures UGC en attente échoué', [
                    'face_id' => $faceId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. Suspensions lifted: {$lifted}. Pending candidatures withdrawn: {$withdrawn}.".($failures > 0 ? " Failures: {$failures}." : ''));

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
