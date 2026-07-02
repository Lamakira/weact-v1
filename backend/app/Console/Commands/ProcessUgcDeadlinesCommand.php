<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UgcTunnelStatus;
use App\Events\UgcDeadlineApproaching;
use App\Models\Shipment;
use App\Services\Ugc\UgcDeadlineService;
use App\Services\Ugc\UgcSuspensionService;
use Illuminate\Console\Command;

class ProcessUgcDeadlinesCommand extends Command
{
    protected $signature = 'ugc:process-deadlines';

    protected $description = 'Escalate UGC deliverable upload deadlines: notify the Face (in-app + Reverb) by threshold, idempotently per shipment.last_notified_threshold';

    public function __construct(
        private readonly UgcDeadlineService $deadlines,
        private readonly UgcSuspensionService $suspensions,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Chronos ACTIFS = la Face doit (encore) uploader. Couvre le re-upload
        // après reject/retouche (revert received/avis_pending, D-4.3.b). D-4.5.b.
        $shipments = Shipment::query()
            ->whereIn('tunnel_status', [
                UgcTunnelStatus::Received->value,
                UgcTunnelStatus::AvisPending->value,
            ])
            ->with('owner')
            ->get();

        $dispatched = 0;

        foreach ($shipments as $shipment) {
            $progress = $this->deadlines->progressFor($shipment);
            if ($progress === null) {
                continue;
            }

            // [5.1] progress >= 1.0 sans livrable validé → suspension douce (l'état actif
            // Received/AvisPending garantit l'absence d'upload validé : un upload validé
            // aurait fait avancer le tunnel hors de ces états).
            if ($progress >= 1.0) {
                $this->suspensions->suspendForOverdueShipment($shipment);

                continue; // suspendu : ne pas aussi escalader une notification ce tick
            }

            $level = $this->deadlines->escalationLevelFor($progress);
            if ($level <= 0) {
                continue; // teal/base : pas de notification
            }

            // Idempotence par seuil (D-4.5.d) : UPDATE conditionnel atomique.
            $advanced = Shipment::query()
                ->whereKey($shipment->getKey())
                ->where('last_notified_threshold', '<', $level)
                ->update(['last_notified_threshold' => $level]);

            if ($advanced === 1) {
                UgcDeadlineApproaching::dispatch($shipment, $level);
                $dispatched++;
            }
        }

        $this->info("Done. Deadline escalation notifications dispatched: {$dispatched}.");

        return self::SUCCESS;
    }
}
